<?php

namespace App\Services\Payment;

use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class FreemopayService
{
    protected FreemopayClient $client;
    protected FreemopayTokenManager $tokenManager;
    protected array $config;

    protected int $pollingInterval = 3;
    protected int $pollingTimeout = 300;
    protected int $maxPollingAttempts = 100;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->client = new FreemopayClient();
        $this->tokenManager = new FreemopayTokenManager($this->client);
    }

    /**
     * Load configuration from settings
     */
    protected function loadConfig(): array
    {
        return [
            'base_url' => Setting::get('freemopay_base_url', 'https://api-v2.freemopay.com'),
            'app_key' => Setting::get('freemopay_app_key'),
            'secret_key' => Setting::get('freemopay_secret_key'),
            'callback_url' => Setting::get('freemopay_callback_url'),
            'init_payment_timeout' => Setting::get('freemopay_init_payment_timeout', 60),
            'status_check_timeout' => Setting::get('freemopay_status_check_timeout', 30),
            'active' => Setting::get('freemopay_active', false),
        ];
    }

    /**
     * Initialize a payment with Freemopay
     */
    public function initPayment(
        User $user,
        float $amount,
        string $phoneNumber,
        string $description
    ): Transaction {
        if (!$this->isConfigured()) {
            throw new \Exception('Freemopay service is not configured properly');
        }

        Log::info("=== Freemopay: Initiating payment ===");
        Log::info("Amount: {$amount} XAF");
        Log::info("Phone: {$phoneNumber}");
        Log::info("Description: {$description}");

        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);
        Log::info("Phone normalized: {$normalizedPhone}");

        $externalId = $this->generateExternalId();
        Log::info("External ID: {$externalId}");

        $callbackUrl = $this->config['callback_url'] ?? config('app.url') . '/api/webhooks/freemopay';
        Log::info("Callback URL: {$callbackUrl}");

        $transaction = DB::transaction(function () use ($user, $amount, $normalizedPhone, $description, $externalId) {
            $paymentMethod = $this->detectPaymentMethod($normalizedPhone);

            return $user->transactions()->create([
                'type' => 'deposit',
                'amount' => $amount,
                'description' => $description,
                'status' => 'pending',
                'meta' => json_encode([
                    'external_id' => $externalId,
                    'payment_method' => 'freemopay',
                    'payment_operator' => $paymentMethod,
                    'phone_number' => $normalizedPhone,
                    'created_at' => now()->toISOString(),
                ])
            ]);
        });

        Log::info("Transaction created in database");
        Log::info("Transaction ID: {$transaction->id}");

        try {
            Log::info("=== Calling Freemopay API ===");

            $freemoResponse = $this->callFreemopayAPI(
                $normalizedPhone,
                $amount,
                $externalId,
                $description,
                $callbackUrl
            );

            $reference = $freemoResponse['reference'] ?? null;

            if (!$reference) {
                Log::error("No reference in Freemopay response");
                Log::error("Response: " . json_encode($freemoResponse));
                $transaction->update(['status' => 'failed']);
                throw new \Exception('No reference in Freemopay response');
            }

            $meta = json_decode($transaction->meta, true);
            $meta['provider_reference'] = $reference;
            $meta['provider_response'] = $freemoResponse;

            $transaction->update([
                'meta' => json_encode($meta)
            ]);

            Log::info("Payment initiated successfully");
            Log::info("Reference: {$reference}");

            $finalTransaction = $this->waitForPaymentCompletion($transaction, $reference);

            Log::info("=== Payment completed successfully ===");
            Log::info("Transaction ID: {$finalTransaction->id}");
            Log::info("Final status: {$finalTransaction->status}");
            Log::info("Amount: {$finalTransaction->amount} XAF");

            return $finalTransaction;

        } catch (\Exception $e) {
            Log::error("=== Payment failed ===");
            Log::error("Transaction ID: {$transaction->id}");
            Log::error("Error: {$e->getMessage()}");

            $transaction->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Poll Freemopay API until payment is completed
     */
    protected function waitForPaymentCompletion(Transaction $transaction, string $reference): Transaction
    {
        Log::info("=== Starting payment polling ===");
        Log::info("Transaction ID: {$transaction->id}");
        Log::info("Reference: {$reference}");
        Log::info("Polling config: Interval={$this->pollingInterval}s, Timeout={$this->pollingTimeout}s");

        $startTime = time();
        $attempts = 0;

        $successStatuses = ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'];
        $failedStatuses = ['FAILED', 'FAILURE', 'ERROR', 'REJECTED', 'CANCELLED', 'CANCELED'];

        while (true) {
            $attempts++;
            $elapsed = time() - $startTime;
            $remainingTime = $this->pollingTimeout - $elapsed;

            Log::info("[Polling Attempt #{$attempts}] Time elapsed: {$elapsed}s / {$this->pollingTimeout}s");

            if ($elapsed >= $this->pollingTimeout) {
                Log::error("Polling timeout reached");
                $transaction->update([
                    'status' => 'pending',
                    'meta' => json_encode(array_merge(
                        json_decode($transaction->meta, true),
                        ['timeout_note' => "Payment polling timeout after {$elapsed}s and {$attempts} attempts"]
                    ))
                ]);
                throw new \Exception("Le délai d'attente du paiement a expiré. Veuillez vérifier votre téléphone et réessayer.");
            }

            if ($attempts > $this->maxPollingAttempts) {
                Log::warning("Max polling attempts reached");
                break;
            }

            try {
                Log::info("Checking payment status with Freemopay API...");

                $statusResponse = $this->checkPaymentStatus($reference);
                $currentStatus = strtoupper($statusResponse['status'] ?? 'UNKNOWN');
                $message = $statusResponse['message'] ?? 'No message';

                Log::info("Received status: {$currentStatus}");

                if (in_array($currentStatus, $successStatuses)) {
                    Log::info("=== Payment SUCCESS ===");
                    Log::info("Transaction ID: {$transaction->id}");
                    Log::info("Completed in: {$elapsed}s after {$attempts} attempts");

                    $transaction->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'meta' => json_encode(array_merge(
                            json_decode($transaction->meta, true),
                            [
                                'status_response' => $statusResponse,
                                'completed_at' => now()->toISOString(),
                                'attempts' => $attempts,
                                'duration' => $elapsed
                            ]
                        ))
                    ]);

                    $this->processDeposit($transaction);
                    return $transaction->fresh();
                }

                if (in_array($currentStatus, $failedStatuses)) {
                    Log::error("=== Payment FAILED ===");
                    Log::error("Transaction ID: {$transaction->id}");
                    Log::error("Reason: {$message}");

                    $transaction->update([
                        'status' => 'failed',
                        'meta' => json_encode(array_merge(
                            json_decode($transaction->meta, true),
                            [
                                'failure_reason' => $message,
                                'status_response' => $statusResponse
                            ]
                        ))
                    ]);
                    throw new \Exception("Le paiement a échoué: {$message}");
                }

                Log::info("Payment still {$currentStatus}, waiting {$this->pollingInterval}s...");
                sleep($this->pollingInterval);

            } catch (\Exception $e) {
                if (str_starts_with($e->getMessage(), 'Le paiement a échoué:') ||
                    str_starts_with($e->getMessage(), 'Le délai d\'attente')) {
                    throw $e;
                }

                Log::warning("Polling error (attempt {$attempts}): " . $e->getMessage());
                sleep($this->pollingInterval);
            }
        }

        return $transaction->fresh();
    }

    /**
     * Call Freemopay API to initialize payment
     */
    protected function callFreemopayAPI(
        string $payer,
        float $amount,
        string $externalId,
        string $description,
        string $callback
    ): array {
        $bearerToken = $this->tokenManager->getToken();

        $payload = [
            'payer' => $payer,
            'amount' => $amount,
            'externalId' => $externalId,
            'description' => $description,
            'callback' => $callback
        ];

        $baseUrl = rtrim($this->config['base_url'], '/');
        $endpoint = "{$baseUrl}/api/v2/payment";

        Log::info("[Freemopay Service] Calling Freemopay API v2");
        Log::info("[Freemopay Service] URL: {$endpoint}");
        Log::info("[Freemopay Service] Payload: " . json_encode($payload));

        $response = $this->client->post(
            $endpoint,
            $payload,
            $bearerToken,
            false,
            $this->config['init_payment_timeout']
        );

        Log::info("[Freemopay Service] Freemopay response: " . json_encode($response));

        $initStatus = strtoupper($response['status'] ?? '');
        $validInitStatuses = ['SUCCESS', 'CREATED', 'PENDING', 'PROCESSING'];
        $failedStatuses = ['FAILED', 'FAILURE', 'ERROR', 'REJECTED', 'CANCELLED', 'CANCELED'];

        if (in_array($initStatus, $failedStatuses)) {
            $errorMessage = $response['message'] ?? 'Unknown error';
            Log::error("[Freemopay Service] Init failed - Status: {$initStatus}, Message: {$errorMessage}");
            throw new \Exception("Payment initialization failed: {$errorMessage}");
        }

        return $response;
    }

    /**
     * Check payment status via Freemopay API
     */
    public function checkPaymentStatus(string $reference): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Freemopay service is not configured properly');
        }

        Log::debug("[Freemopay Service] Checking payment status - Reference: {$reference}");

        $bearerToken = $this->tokenManager->getToken();

        $baseUrl = rtrim($this->config['base_url'], '/');
        $endpoint = "{$baseUrl}/api/v2/payment/{$reference}";

        $response = $this->client->get(
            $endpoint,
            $bearerToken,
            false,
            $this->config['status_check_timeout']
        );

        Log::debug("[Freemopay Service] Status check response: " . json_encode($response));

        return $response;
    }

    /**
     * Process deposit and credit user wallet
     */
    protected function processDeposit(Transaction $transaction): void
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

        Log::info('Deposit processed', [
            'user_id' => $user->id,
            'amount' => $transaction->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter
        ]);
    }

    /**
     * Normalize phone number for Freemopay
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        if (!$phone) {
            throw new \Exception('Phone number is required');
        }

        $cleaned = preg_replace('/[\s\-+]/', '', $phone);

        if (!str_starts_with($cleaned, '237') && !str_starts_with($cleaned, '243')) {
            if (strlen($cleaned) === 9 && str_starts_with($cleaned, '6')) {
                $cleaned = '237' . $cleaned;
                Log::info("[Freemopay Service] Auto-normalized phone: Added 237 prefix");
            } else {
                throw new \Exception("Invalid phone number format. Expected 237XXXXXXXXX or 9-digit number starting with 6: {$phone}");
            }
        }

        if (strlen($cleaned) !== 12 || !ctype_digit($cleaned)) {
            throw new \Exception("Invalid phone format. Expected 12 digits (237XXXXXXXXX): {$phone}");
        }

        return $cleaned;
    }

    /**
     * Generate a unique external ID
     */
    protected function generateExternalId(string $prefix = 'DEP'): string
    {
        $timestamp = now()->format('YmdHis');
        $random = substr(uniqid(), -4);
        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Detect payment method based on phone number prefix
     */
    protected function detectPaymentMethod(string $phone): string
    {
        if (str_starts_with($phone, '237')) {
            $prefix = substr($phone, 3, 2);
            $prefix3 = substr($phone, 3, 3);

            if (in_array($prefix, ['67', '68']) || in_array($prefix3, ['650', '651', '652', '653', '654'])) {
                return 'MTN';
            }

            if ($prefix === '69' || in_array($prefix3, ['655', '656', '657', '658', '659'])) {
                return 'ORANGE';
            }
        }

        return 'MTN';
    }

    /**
     * Test Freemopay connection
     */
    public function testConnection(): array
    {
        try {
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Freemopay configuration is incomplete',
                    'data' => null
                ];
            }

            $this->tokenManager->clearToken();
            $token = $this->tokenManager->getToken();

            return [
                'success' => true,
                'message' => 'Freemopay connection successful, token generated',
                'data' => [
                    'token_length' => strlen($token),
                    'token_preview' => substr($token, 0, 20) . '...'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Connection test failed: {$e->getMessage()}",
                'data' => null
            ];
        }
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->config['app_key']) &&
               !empty($this->config['secret_key']) &&
               $this->config['active'] === true;
    }

    /**
     * Set polling configuration
     */
    public function setPollingConfig(int $interval = 3, int $timeout = 90): self
    {
        $this->pollingInterval = $interval;
        $this->pollingTimeout = $timeout;
        $this->maxPollingAttempts = (int) ceil($timeout / $interval);
        return $this;
    }
}
