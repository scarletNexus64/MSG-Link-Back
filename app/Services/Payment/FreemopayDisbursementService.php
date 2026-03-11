<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FreemopayDisbursementService
{
    protected array $config;
    protected string $baseUrl;
    protected ?string $appKey;
    protected ?string $secretKey;

    protected int $pollingInterval = 3;
    protected int $pollingTimeout = 90;
    protected int $maxPollingAttempts = 30;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->baseUrl = rtrim($this->config['base_url'], '/');
        $this->appKey = $this->config['app_key'];
        $this->secretKey = $this->config['secret_key'];
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
            'active' => Setting::get('freemopay_active', false),
        ];
    }

    /**
     * Check if Freemopay is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->appKey) && !empty($this->secretKey);
    }

    /**
     * Normalize phone number to international format
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (preg_match('/^(237|243)/', $phone)) {
            return $phone;
        }

        if (preg_match('/^6\d{8}$/', $phone)) {
            return '237' . $phone;
        }

        if (preg_match('/^[89]\d{8}$/', $phone)) {
            return '243' . $phone;
        }

        throw new \Exception('Format de numéro invalide. Utilisez le format: 237XXXXXXXXX ou 243XXXXXXXXX');
    }

    /**
     * Initialize a withdrawal (cashout)
     *
     * API endpoint: POST /api/v2/payment/direct-withdraw
     */
    public function initWithdrawal(
        string $receiver,
        float $amount,
        string $externalId,
        string $callback
    ): array {
        if (!$this->isConfigured()) {
            throw new \Exception('Freemopay n\'est pas configuré.');
        }

        $endpoint = "{$this->baseUrl}/api/v2/payment/direct-withdraw";

        $payload = [
            'receiver' => $receiver,
            'amount' => $amount,
            'externalId' => $externalId,
            'callback' => $callback
        ];

        Log::info("[Freemopay Disbursement] Initiating withdrawal");
        Log::info("[Freemopay Disbursement] Endpoint: {$endpoint}");
        Log::info("[Freemopay Disbursement] Payload: " . json_encode($payload));

        try {
            $response = Http::withBasicAuth($this->appKey, $this->secretKey)
                ->timeout(30)
                ->post($endpoint, $payload);

            Log::info("[Freemopay Disbursement] HTTP Status: {$response->status()}");

            if (!$response->successful()) {
                $errorBody = $response->json() ?? ['message' => $response->body()];
                $errorMessage = is_array($errorBody['message'] ?? null)
                    ? implode(', ', $errorBody['message'])
                    : ($errorBody['message'] ?? "Erreur HTTP {$response->status()}");

                Log::error("[Freemopay Disbursement] Erreur API: {$errorMessage}");
                throw new \Exception("Erreur lors de l'initialisation du retrait: {$errorMessage}");
            }

            $data = $response->json();
            Log::info("[Freemopay Disbursement] Response: " . json_encode($data));

            return $data;

        } catch (\Exception $e) {
            Log::error("[Freemopay Disbursement] Exception: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check withdrawal status by reference
     *
     * API endpoint: GET /api/v2/payment/{reference}
     */
    public function checkWithdrawalStatus(string $reference): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Freemopay n\'est pas configuré.');
        }

        $endpoint = "{$this->baseUrl}/api/v2/payment/{$reference}";

        Log::debug("[Freemopay Disbursement] Checking withdrawal status - Reference: {$reference}");
        Log::debug("[Freemopay Disbursement] Endpoint: {$endpoint}");

        try {
            $response = Http::withBasicAuth($this->appKey, $this->secretKey)
                ->timeout(30)
                ->get($endpoint);

            Log::debug("[Freemopay Disbursement] HTTP Status: {$response->status()}");

            if (!$response->successful()) {
                $errorBody = $response->json() ?? ['message' => $response->body()];
                $errorMessage = is_array($errorBody['message'] ?? null)
                    ? implode(', ', $errorBody['message'])
                    : ($errorBody['message'] ?? "Erreur HTTP {$response->status()}");

                Log::error("[Freemopay Disbursement] Erreur API: {$errorMessage}");
                throw new \Exception("Erreur vérification statut: {$errorMessage}");
            }

            $data = $response->json();
            Log::debug("[Freemopay Disbursement] Status received: " . json_encode($data));

            return $data;

        } catch (\Exception $e) {
            Log::error("[Freemopay Disbursement] Exception: " . $e->getMessage());
            throw $e;
        }
    }
}
