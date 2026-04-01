<?php

namespace App\Jobs\Wallet;

use App\Models\Transaction;
use App\Models\WalletTransaction;
use App\Services\Payment\FreemopayService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Job qui vérifie le statut d'un deposit spécifique via l'API FreeMoPay
 */
class ProcessDepositStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = 10; // Retry après 10 secondes en cas d'erreur

    public function __construct(
        public int $transactionId
    ) {}

    public function handle(FreemopayService $freemopayService, NotificationService $notificationService)
    {
        $transaction = Transaction::find($this->transactionId);

        if (!$transaction) {
            Log::warning('⚠️ [PROCESS-DEPOSIT] Transaction not found', [
                'transaction_id' => $this->transactionId,
            ]);
            return;
        }

        // Si déjà traité, on arrête
        if (in_array($transaction->status, ['completed', 'failed', 'cancelled'])) {
            Log::debug('ℹ️ [PROCESS-DEPOSIT] Transaction already processed', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
            ]);
            return;
        }

        $meta = json_decode($transaction->meta, true) ?? [];
        $reference = $meta['provider_reference'] ?? null;

        if (!$reference) {
            Log::error('❌ [PROCESS-DEPOSIT] No FreeMoPay reference found', [
                'transaction_id' => $transaction->id,
            ]);
            return;
        }

        try {
            Log::info('🔍 [PROCESS-DEPOSIT] Checking deposit status with FreeMoPay API', [
                'transaction_id' => $transaction->id,
                'reference' => $reference,
                'attempt' => $this->attempts(),
            ]);

            // Appeler l'API FreeMoPay pour obtenir le statut
            $statusResponse = $freemopayService->checkPaymentStatus($reference);

            $status = strtoupper($statusResponse['status'] ?? 'UNKNOWN');
            $reason = $statusResponse['reason'] ?? $statusResponse['message'] ?? null;

            Log::info('📊 [PROCESS-DEPOSIT] FreeMoPay status received', [
                'transaction_id' => $transaction->id,
                'status' => $status,
                'reason' => $reason,
            ]);

            // Traiter selon le statut
            DB::transaction(function () use ($transaction, $status, $reason, $statusResponse, $meta, $notificationService) {
                // Mettre à jour les meta avec la réponse
                $meta['last_status_check'] = now()->toISOString();
                $meta['freemopay_status'] = $status;
                $meta['freemopay_response'] = $statusResponse;

                if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'])) {
                    Log::info('✅ [PROCESS-DEPOSIT] Payment SUCCESS - Crediting wallet', [
                        'transaction_id' => $transaction->id,
                        'amount' => $transaction->amount,
                    ]);

                    // Update transaction
                    $transaction->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'meta' => json_encode(array_merge($meta, [
                            'completed_via' => 'job',
                            'completed_at' => now()->toISOString(),
                        ])),
                    ]);

                    // Créditer le wallet
                    $this->creditWallet($transaction);

                    Log::info('💰 [PROCESS-DEPOSIT] Wallet credited successfully', [
                        'transaction_id' => $transaction->id,
                        'user_id' => $transaction->user_id,
                    ]);

                    // Envoyer notification FCM à l'utilisateur
                    try {
                        $notificationService->sendDepositCompletedNotification($transaction);
                        Log::info('📱 [PROCESS-DEPOSIT] FCM notification sent', [
                            'transaction_id' => $transaction->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('❌ [PROCESS-DEPOSIT] Failed to send FCM notification', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                } elseif (in_array($status, ['FAILED', 'FAILURE', 'ERROR', 'REJECTED', 'CANCELLED', 'CANCELED'])) {
                    Log::warning('⚠️ [PROCESS-DEPOSIT] Payment FAILED', [
                        'transaction_id' => $transaction->id,
                        'reason' => $reason,
                    ]);

                    $transaction->update([
                        'status' => 'failed',
                        'meta' => json_encode(array_merge($meta, [
                            'failure_reason' => $reason ?? 'Payment failed',
                            'failed_at' => now()->toISOString(),
                        ])),
                    ]);

                    // Envoyer notification FCM d'échec
                    try {
                        $notificationService->sendDepositFailedNotification($transaction, $reason);
                        Log::info('📱 [PROCESS-DEPOSIT] FCM failure notification sent', [
                            'transaction_id' => $transaction->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('❌ [PROCESS-DEPOSIT] Failed to send FCM failure notification', [
                            'transaction_id' => $transaction->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                } elseif (in_array($status, ['PENDING', 'PROCESSING', 'INITIATED'])) {
                    Log::debug('⏳ [PROCESS-DEPOSIT] Payment still pending', [
                        'transaction_id' => $transaction->id,
                        'status' => $status,
                    ]);

                    // Simplement mettre à jour les meta, la prochaine vérification se fera au prochain run
                    $transaction->update([
                        'meta' => json_encode($meta),
                    ]);

                } else {
                    Log::warning('⚠️ [PROCESS-DEPOSIT] Unknown status received', [
                        'transaction_id' => $transaction->id,
                        'status' => $status,
                        'response' => $statusResponse,
                    ]);

                    // Mettre à jour les meta quand même
                    $transaction->update([
                        'meta' => json_encode($meta),
                    ]);
                }
            });

        } catch (\Exception $e) {
            Log::error('❌ [PROCESS-DEPOSIT] Error checking deposit status', [
                'transaction_id' => $this->transactionId,
                'reference' => $reference,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Laravel va automatiquement retry selon $tries
            throw $e;
        }
    }

    /**
     * Créditer le wallet de l'utilisateur
     */
    private function creditWallet(Transaction $transaction): void
    {
        $user = $transaction->user;
        $balanceBefore = $user->wallet_balance;

        // Incrémenter le solde
        $user->increment('wallet_balance', $transaction->amount);

        $balanceAfter = $user->fresh()->wallet_balance;

        // Créer la transaction wallet
        WalletTransaction::create([
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_CREDIT,
            'amount' => $transaction->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => 'Dépôt via FreeMoPay',
            'reference' => json_decode($transaction->meta, true)['external_id'] ?? null,
            'transactionable_type' => Transaction::class,
            'transactionable_id' => $transaction->id,
        ]);

        Log::info('💰 [PROCESS-DEPOSIT] WalletTransaction created', [
            'user_id' => $user->id,
            'amount' => $transaction->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ]);
    }

    /**
     * Gérer l'échec du job après tous les retries
     */
    public function failed(\Throwable $exception)
    {
        Log::error('❌ [PROCESS-DEPOSIT] Job failed after all retries', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionnel : Notifier un admin, créer une alerte, etc.
    }
}
