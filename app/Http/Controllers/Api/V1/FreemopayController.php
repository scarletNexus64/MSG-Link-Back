<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payment\FreemopayService;
use App\Services\Payment\FreemopayDisbursementService;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class FreemopayController extends Controller
{
    protected FreemopayService $freemopayService;
    protected FreemopayDisbursementService $disbursementService;

    public function __construct()
    {
        $this->freemopayService = new FreemopayService();
        $this->disbursementService = new FreemopayDisbursementService();
    }

    /**
     * Initiate a deposit payment for wallet recharge
     */
    public function initiateDepositPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'phone_number' => 'nullable|string',
        ]);

        $user = $request->user();
        $amount = (int) $request->amount;
        $phoneNumber = $request->phone_number ?? $user->phone ?? '+237600000000';

        Log::info('=== Freemopay Deposit Request ===', [
            'user_id' => $user->id,
            'amount' => $amount,
            'phone' => $phoneNumber
        ]);

        try {
            $transaction = $this->freemopayService->initPayment(
                $user,
                $amount,
                $phoneNumber,
                'Dépôt de fonds wallet MSG-Link'
            );

            return response()->json([
                'success' => true,
                'message' => 'Paiement initié avec succès',
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'amount' => $transaction->amount
            ]);

        } catch (Exception $e) {
            Log::error('Freemopay Deposit Exception', [
                'message' => $e->getMessage(),
                'amount' => $amount,
                'phone' => $phoneNumber
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check deposit transaction status
     */
    public function checkTransactionStatus(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id'
        ]);

        $user = $request->user();
        $transaction = Transaction::find($request->transaction_id);

        if (!$transaction || $transaction->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }

        $meta = json_decode($transaction->meta, true);
        $reference = $meta['provider_reference'] ?? null;

        if (!$reference) {
            return response()->json([
                'success' => true,
                'status' => $transaction->status,
                'message' => 'Transaction en attente',
                'amount' => $transaction->amount,
                'created_at' => $transaction->created_at
            ]);
        }

        try {
            $statusResponse = $this->freemopayService->checkPaymentStatus($reference);
            $freemopayStatus = strtoupper($statusResponse['status'] ?? 'UNKNOWN');
            $message = $statusResponse['message'] ?? '';

            // Update transaction status if needed
            $successStatuses = ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'];
            $failedStatuses = ['FAILED', 'FAILURE', 'ERROR', 'REJECTED', 'CANCELLED', 'CANCELED'];

            if (in_array($freemopayStatus, $successStatuses) && $transaction->status !== 'completed') {
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'meta' => json_encode(array_merge($meta, [
                        'status_response' => $statusResponse,
                        'verified_at' => now()->toISOString()
                    ]))
                ]);

                // Process deposit
                $this->processDeposit($transaction);

            } elseif (in_array($freemopayStatus, $failedStatuses) && $transaction->status !== 'failed') {
                $transaction->update([
                    'status' => 'failed',
                    'meta' => json_encode(array_merge($meta, [
                        'failure_reason' => $message,
                        'status_response' => $statusResponse
                    ]))
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => $transaction->fresh()->status,
                'freemopay_status' => $freemopayStatus,
                'message' => $message,
                'amount' => $transaction->amount,
                'created_at' => $transaction->created_at
            ]);

        } catch (Exception $e) {
            Log::error('Freemopay Status Check Exception', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => true,
                'status' => $transaction->status,
                'message' => 'Transaction en cours de traitement',
                'amount' => $transaction->amount,
                'created_at' => $transaction->created_at
            ]);
        }
    }

    /**
     * Initiate a withdrawal
     */
    public function initiateWithdrawal(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000|multiple_of:5',
            'phone_number' => 'required|string',
            'operator' => 'nullable|string|in:MTN,ORANGE,MOOV'
        ]);

        $user = $request->user();
        $amount = $request->amount;
        $phoneNumber = $request->phone_number;
        $operator = $request->input('operator', null);

        // Check available balance
        $availableForWithdrawal = max(0, $user->wallet_balance);

        if ($amount > $availableForWithdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Solde insuffisant pour ce retrait'
            ], 400);
        }

        Log::info('=== Freemopay Withdrawal Request ===', [
            'user_id' => $user->id,
            'amount' => $amount,
            'phone' => $phoneNumber,
            'operator' => $operator
        ]);

        try {
            // Normalize phone
            $normalizedPhone = $this->disbursementService->normalizePhoneNumber($phoneNumber);

            // Generate external ID
            $externalId = 'WDW-' . now()->format('YmdHis') . '-' . substr(uniqid(), -4);

            // Get callback URL
            $callbackUrl = config('app.url') . '/api/webhooks/freemopay';

            // Create pending withdrawal transaction
            $transaction = $user->transactions()->create([
                'type' => 'withdrawal',
                'amount' => -$amount,
                'description' => "Retrait vers " . ($operator ?: 'Auto') . " - {$phoneNumber}",
                'status' => 'pending',
                'meta' => json_encode([
                    'operator' => $operator,
                    'phone_number' => $normalizedPhone,
                    'external_id' => $externalId,
                    'payment_method' => 'freemopay',
                    'requested_at' => now()->toISOString(),
                ])
            ]);

            // Call Freemopay API
            $freemoResponse = $this->disbursementService->initWithdrawal(
                $normalizedPhone,
                $amount,
                $externalId,
                $callbackUrl
            );

            $reference = $freemoResponse['reference'] ?? null;

            if ($reference) {
                $meta = json_decode($transaction->meta, true);
                $meta['provider_reference'] = $reference;
                $meta['provider_response'] = $freemoResponse;

                $transaction->update([
                    'meta' => json_encode($meta)
                ]);
            }

            Log::info('Freemopay withdrawal initiated', [
                'transaction_id' => $transaction->id,
                'reference' => $reference
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Votre demande de retrait a été enregistrée et est en cours de traitement.',
                'transaction_id' => $transaction->id,
                'status' => 'pending',
                'current_balance' => $user->wallet_balance,
                'reference' => $reference
            ]);

        } catch (Exception $e) {
            Log::error('Freemopay Withdrawal Exception', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de la demande: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check withdrawal status
     */
    public function checkWithdrawalStatus(Request $request)
    {
        $request->validate([
            'transaction_id' => 'required|integer|exists:transactions,id'
        ]);

        $user = $request->user();
        $transaction = Transaction::find($request->transaction_id);

        if (!$transaction || $transaction->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction non trouvée'
            ], 404);
        }

        $meta = json_decode($transaction->meta, true);
        $reference = $meta['provider_reference'] ?? null;

        if (!$reference) {
            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at
                ]
            ]);
        }

        try {
            $statusResponse = $this->disbursementService->checkWithdrawalStatus($reference);
            $freemopayStatus = strtoupper($statusResponse['status'] ?? 'UNKNOWN');
            $message = $statusResponse['message'] ?? '';

            // Update transaction status if needed
            $successStatuses = ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'];
            $failedStatuses = ['FAILED', 'FAILURE', 'ERROR', 'REJECTED', 'CANCELLED', 'CANCELED'];

            if (in_array($freemopayStatus, $successStatuses) && $transaction->status !== 'completed') {
                $transaction->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'meta' => json_encode(array_merge($meta, [
                        'status_response' => $statusResponse,
                        'completed_at' => now()->toISOString()
                    ]))
                ]);

                // Debit wallet
                $this->processWithdrawal($transaction);

            } elseif (in_array($freemopayStatus, $failedStatuses) && $transaction->status !== 'failed') {
                $transaction->update([
                    'status' => 'failed',
                    'meta' => json_encode(array_merge($meta, [
                        'failure_reason' => $message,
                        'status_response' => $statusResponse
                    ]))
                ]);
            }

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'status' => $transaction->fresh()->status,
                    'freemopay_status' => $freemopayStatus,
                    'message' => $message,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Freemopay Withdrawal Status Check Exception', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => true,
                'transaction' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'status' => $transaction->status,
                    'created_at' => $transaction->created_at,
                    'updated_at' => $transaction->updated_at
                ]
            ]);
        }
    }

    /**
     * Handle Freemopay webhook callback
     */
    public function handleCallback(Request $request)
    {
        Log::info('=== Freemopay Callback Received ===', [
            'body' => $request->all()
        ]);

        $status = strtoupper($request->input('status'));
        $reference = $request->input('reference');
        $amount = $request->input('amount');
        $transactionType = $request->input('transactionType');
        $externalId = $request->input('externalId');
        $message = $request->input('message');

        // Find transaction by external_id
        $transaction = Transaction::where('meta->external_id', $externalId)->first();

        if (!$transaction) {
            Log::error('Freemopay Callback: Transaction not found', ['external_id' => $externalId]);
            return response()->json(['status' => 'error', 'message' => 'Transaction not found'], 404);
        }

        Log::info('Freemopay Callback: Transaction found', [
            'transaction_id' => $transaction->id,
            'current_status' => $transaction->status,
            'freemopay_status' => $status
        ]);

        $successStatuses = ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'];
        $failedStatuses = ['FAILED', 'FAILURE', 'ERROR', 'REJECTED', 'CANCELLED', 'CANCELED'];

        $meta = json_decode($transaction->meta, true);

        if (in_array($status, $successStatuses) && $transaction->status !== 'completed') {
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
                'meta' => json_encode(array_merge($meta, [
                    'callback_data' => $request->all(),
                    'completed_at' => now()->toISOString()
                ]))
            ]);

            if ($transaction->type === 'deposit') {
                $this->processDeposit($transaction);
            } elseif ($transaction->type === 'withdrawal') {
                $this->processWithdrawal($transaction);
            }

            Log::info('Freemopay Callback: Transaction marked as completed', [
                'transaction_id' => $transaction->id
            ]);

        } elseif (in_array($status, $failedStatuses) && $transaction->status !== 'failed') {
            $transaction->update([
                'status' => 'failed',
                'meta' => json_encode(array_merge($meta, [
                    'failure_reason' => $message,
                    'callback_data' => $request->all()
                ]))
            ]);

            Log::info('Freemopay Callback: Transaction marked as failed', [
                'transaction_id' => $transaction->id,
                'reason' => $message
            ]);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Process deposit
     */
    private function processDeposit($transaction)
    {
        if ($transaction->type !== 'deposit') {
            return;
        }

        $user = $transaction->user;
        $balanceBefore = $user->wallet_balance;

        $user->increment('wallet_balance', $transaction->amount);

        $balanceAfter = $user->fresh()->wallet_balance;

        \App\Models\WalletTransaction::create([
            'user_id' => $user->id,
            'type' => \App\Models\WalletTransaction::TYPE_CREDIT,
            'amount' => $transaction->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => 'Dépôt via Freemopay',
            'reference' => json_decode($transaction->meta, true)['external_id'] ?? null,
            'transactionable_type' => Transaction::class,
            'transactionable_id' => $transaction->id,
        ]);

        Log::info('Deposit Processed', [
            'user_id' => $user->id,
            'amount' => $transaction->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter
        ]);
    }

    /**
     * Process withdrawal
     */
    private function processWithdrawal($transaction)
    {
        if ($transaction->type !== 'withdrawal') {
            return;
        }

        $user = $transaction->user;
        $balanceBefore = $user->wallet_balance;
        $amount = abs($transaction->amount);

        $user->decrement('wallet_balance', $amount);

        $balanceAfter = $user->fresh()->wallet_balance;

        \App\Models\WalletTransaction::create([
            'user_id' => $user->id,
            'type' => \App\Models\WalletTransaction::TYPE_DEBIT,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => 'Retrait via Freemopay',
            'reference' => json_decode($transaction->meta, true)['external_id'] ?? null,
            'transactionable_type' => Transaction::class,
            'transactionable_id' => $transaction->id,
        ]);

        Log::info('Withdrawal Processed', [
            'user_id' => $user->id,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter
        ]);
    }
}
