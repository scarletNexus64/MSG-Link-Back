<?php

namespace App\Jobs\Wallet;

use App\Models\Withdrawal;
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
 * Job qui vérifie le statut d'un withdrawal spécifique via l'API FreeMoPay
 */
class ProcessWithdrawalStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = 10;

    public function __construct(
        public int $withdrawalId
    ) {}

    public function handle(FreemopayService $freemopayService, NotificationService $notificationService)
    {
        $withdrawal = Withdrawal::find($this->withdrawalId);

        if (!$withdrawal) {
            Log::warning('⚠️ [PROCESS-WITHDRAWAL] Withdrawal not found', [
                'withdrawal_id' => $this->withdrawalId,
            ]);
            return;
        }

        // Si déjà traité, on arrête
        if (in_array($withdrawal->status, [
            Withdrawal::STATUS_COMPLETED,
            Withdrawal::STATUS_FAILED,
            Withdrawal::STATUS_REJECTED
        ])) {
            Log::debug('ℹ️ [PROCESS-WITHDRAWAL] Withdrawal already processed', [
                'withdrawal_id' => $withdrawal->id,
                'status' => $withdrawal->status,
            ]);
            return;
        }

        $reference = $withdrawal->transaction_reference;

        if (!$reference) {
            Log::error('❌ [PROCESS-WITHDRAWAL] No FreeMoPay reference found', [
                'withdrawal_id' => $withdrawal->id,
            ]);
            return;
        }

        try {
            Log::info('🔍 [PROCESS-WITHDRAWAL] Checking withdrawal status with FreeMoPay API', [
                'withdrawal_id' => $withdrawal->id,
                'reference' => $reference,
                'attempt' => $this->attempts(),
            ]);

            // Appeler l'API FreeMoPay pour obtenir le statut
            $statusResponse = $freemopayService->checkPaymentStatus($reference);

            $status = strtoupper($statusResponse['status'] ?? 'UNKNOWN');
            $reason = $statusResponse['reason'] ?? $statusResponse['message'] ?? null;

            Log::info('📊 [PROCESS-WITHDRAWAL] FreeMoPay status received', [
                'withdrawal_id' => $withdrawal->id,
                'status' => $status,
                'reason' => $reason,
            ]);

            // Traiter selon le statut (lock to avoid double-processing/debit)
            DB::transaction(function () use ($withdrawal, $status, $reason, $statusResponse, $notificationService) {
                $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->first();

                if (!$withdrawal) {
                    return;
                }

                if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'])) {
                    Log::info('✅ [PROCESS-WITHDRAWAL] Withdrawal SUCCESS - Debiting wallet', [
                        'withdrawal_id' => $withdrawal->id,
                        'amount' => $withdrawal->amount,
                    ]);

                    // Update withdrawal
                    $withdrawal->update([
                        'status' => Withdrawal::STATUS_COMPLETED,
                        'processed_at' => now(),
                        'notes' => 'Processed via FreeMoPay job',
                    ]);

                    // Débiter le wallet de l'utilisateur
                    $this->debitWallet($withdrawal);

                    Log::info('💸 [PROCESS-WITHDRAWAL] Wallet debited successfully', [
                        'withdrawal_id' => $withdrawal->id,
                        'user_id' => $withdrawal->user_id,
                    ]);

                    // Envoyer notification FCM à l'utilisateur
                    try {
                        $notificationService->sendWithdrawalProcessedNotification($withdrawal);
                        Log::info('📱 [PROCESS-WITHDRAWAL] FCM notification sent', [
                            'withdrawal_id' => $withdrawal->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('❌ [PROCESS-WITHDRAWAL] Failed to send FCM notification', [
                            'withdrawal_id' => $withdrawal->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                } elseif (in_array($status, ['FAILED', 'FAILURE', 'ERROR', 'REJECTED', 'CANCELLED', 'CANCELED'])) {
                    Log::warning('⚠️ [PROCESS-WITHDRAWAL] Withdrawal FAILED', [
                        'withdrawal_id' => $withdrawal->id,
                        'reason' => $reason,
                    ]);

                    $withdrawal->update([
                        'status' => Withdrawal::STATUS_FAILED,
                        'rejection_reason' => $reason ?? 'Withdrawal failed',
                        'processed_at' => now(),
                    ]);

                    // Envoyer notification FCM d'échec
                    try {
                        $notificationService->sendWithdrawalFailedNotification($withdrawal);
                        Log::info('📱 [PROCESS-WITHDRAWAL] FCM failure notification sent', [
                            'withdrawal_id' => $withdrawal->id,
                        ]);
                    } catch (\Exception $e) {
                        Log::error('❌ [PROCESS-WITHDRAWAL] Failed to send FCM failure notification', [
                            'withdrawal_id' => $withdrawal->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                } elseif (in_array($status, ['PENDING', 'PROCESSING', 'INITIATED', 'CREATED'])) {
                    Log::debug('⏳ [PROCESS-WITHDRAWAL] Withdrawal still pending', [
                        'withdrawal_id' => $withdrawal->id,
                        'status' => $status,
                    ]);

                    // Optionnel : passer en "processing" si c'était "pending"
                    if ($withdrawal->status === Withdrawal::STATUS_PENDING) {
                        $withdrawal->update([
                            'status' => Withdrawal::STATUS_PROCESSING,
                        ]);
                    }

                } else {
                    Log::warning('⚠️ [PROCESS-WITHDRAWAL] Unknown status received', [
                        'withdrawal_id' => $withdrawal->id,
                        'status' => $status,
                        'response' => $statusResponse,
                    ]);
                }
            });

        } catch (\Exception $e) {
            Log::error('❌ [PROCESS-WITHDRAWAL] Error checking withdrawal status', [
                'withdrawal_id' => $this->withdrawalId,
                'reference' => $reference,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Laravel va automatiquement retry selon $tries
            throw $e;
        }
    }

    /**
     * Débiter le wallet de l'utilisateur
     */
    private function debitWallet(Withdrawal $withdrawal): void
    {
        // Idempotency: avoid double debits if the status job is executed more than once.
        if ($withdrawal->walletTransaction()->exists()) {
            Log::warning('⚠️ [PROCESS-WITHDRAWAL] Wallet already debited for withdrawal (walletTransaction exists)', [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $withdrawal->user_id,
            ]);
            return;
        }

        $user = $withdrawal->user;
        $balanceBefore = $user->wallet_balance;

        // Décrémenter le solde
        $user->decrement('wallet_balance', $withdrawal->amount);

        $balanceAfter = $user->fresh()->wallet_balance;

        // Créer la transaction wallet
        WalletTransaction::create([
            'user_id' => $user->id,
            'type' => WalletTransaction::TYPE_DEBIT,
            'amount' => $withdrawal->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => "Retrait {$withdrawal->providerLabel} - {$withdrawal->phoneNumber}",
            'reference' => $withdrawal->transaction_reference,
            'transactionable_type' => Withdrawal::class,
            'transactionable_id' => $withdrawal->id,
        ]);

        Log::info('💸 [PROCESS-WITHDRAWAL] WalletTransaction created', [
            'user_id' => $user->id,
            'amount' => $withdrawal->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ]);
    }

    /**
     * Gérer l'échec du job après tous les retries
     */
    public function failed(\Throwable $exception)
    {
        Log::error('❌ [PROCESS-WITHDRAWAL] Job failed after all retries', [
            'withdrawal_id' => $this->withdrawalId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Optionnel : Notifier un admin, créer une alerte, etc.
    }
}
