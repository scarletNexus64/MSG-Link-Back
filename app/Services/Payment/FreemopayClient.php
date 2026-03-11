<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FreemopayClient
{
    protected $config;

    public function __construct()
    {
        $this->config = $this->loadConfig();
    }

    /**
     * Load Freemopay configuration from settings
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
            'token_timeout' => Setting::get('freemopay_token_timeout', 30),
            'active' => Setting::get('freemopay_active', false),
        ];
    }

    /**
     * Make a POST request to Freemopay API
     */
    public function post(
        string $endpoint,
        array $data,
        ?string $bearerToken = null,
        bool $useBasicAuth = false,
        ?int $timeout = null
    ): array {
        $url = $this->buildUrl($endpoint);
        $timeout = $timeout ?? $this->config['init_payment_timeout'];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'MSGLink-App/1.0'
        ];

        $this->logRequest('POST', $url, $headers, $data);

        $startTime = microtime(true);

        try {
            $http = Http::withHeaders($headers)->timeout($timeout);

            if ($bearerToken) {
                $http = $http->withToken($bearerToken);
            } elseif ($useBasicAuth) {
                $http = $http->withBasicAuth(
                    $this->config['app_key'],
                    $this->config['secret_key']
                );
            }

            $response = $http->post($url, $data);

            $duration = microtime(true) - $startTime;

            $this->logResponse($response->status(), $response->body(), $duration);

            Log::info("[Freemopay Client] Status Code: {$response->status()}");
            Log::info("[Freemopay Client] Response Body (raw): {$response->body()}");

            return $this->handleResponse($response);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("[Freemopay] Connection error: " . $e->getMessage());
            throw new \Exception("Connection error: {$e->getMessage()}");

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error("[Freemopay] Request error: " . $e->getMessage());
            throw new \Exception("Request failed: {$e->getMessage()}");

        } catch (\Exception $e) {
            Log::error("[Freemopay] Unexpected error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Make a GET request to Freemopay API
     */
    public function get(
        string $endpoint,
        ?string $bearerToken = null,
        bool $useBasicAuth = false,
        ?int $timeout = null
    ): array {
        $url = $this->buildUrl($endpoint);
        $timeout = $timeout ?? $this->config['status_check_timeout'];

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'MSGLink-App/1.0'
        ];

        $this->logRequest('GET', $url, $headers);

        $startTime = microtime(true);

        try {
            $http = Http::withHeaders($headers)->timeout($timeout);

            if ($bearerToken) {
                $http = $http->withToken($bearerToken);
            } elseif ($useBasicAuth) {
                $http = $http->withBasicAuth(
                    $this->config['app_key'],
                    $this->config['secret_key']
                );
            }

            $response = $http->get($url);

            $duration = microtime(true) - $startTime;

            $this->logResponse($response->status(), $response->body(), $duration);

            return $this->handleResponse($response);

        } catch (\Exception $e) {
            Log::error("[Freemopay] Request error: " . $e->getMessage());
            throw new \Exception("Request failed: {$e->getMessage()}");
        }
    }

    /**
     * Build full URL from endpoint
     */
    protected function buildUrl(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            return $endpoint;
        }

        $base = rtrim($this->config['base_url'], '/');
        $endpoint = ltrim($endpoint, '/');
        return "{$base}/{$endpoint}";
    }

    /**
     * Log request details
     */
    protected function logRequest(string $method, string $url, array $headers, ?array $data = null): void
    {
        $safeHeaders = $headers;
        if (isset($safeHeaders['Authorization'])) {
            $authType = explode(' ', $safeHeaders['Authorization'])[0] ?? '';
            $safeHeaders['Authorization'] = "{$authType} [HIDDEN]";
        }

        Log::debug("[Freemopay] {$method} {$url}");
        Log::debug("[Freemopay] Headers: " . json_encode($safeHeaders));

        if ($data) {
            $safeData = $this->maskSensitiveData($data);
            Log::debug("[Freemopay] Body: " . json_encode($safeData));
        }
    }

    /**
     * Mask sensitive data for logging
     */
    protected function maskSensitiveData(array $data): array
    {
        $safeData = $data;
        $sensitiveKeys = ['secretKey', 'secret_key', 'password', 'token'];

        foreach ($sensitiveKeys as $key) {
            if (isset($safeData[$key])) {
                $safeData[$key] = '[HIDDEN]';
            }
        }

        return $safeData;
    }

    /**
     * Log response details
     */
    protected function logResponse(int $statusCode, string $responseBody, float $duration): void
    {
        $bodyPreview = strlen($responseBody) > 500
            ? substr($responseBody, 0, 500) . '...'
            : $responseBody;

        Log::debug("[Freemopay] Response {$statusCode} in " . number_format($duration, 2) . "s");
        Log::debug("[Freemopay] Body: {$bodyPreview}");

        if ($duration > 3.0) {
            Log::warning("[Freemopay] Slow request: " . number_format($duration, 2) . "s");
        }
    }

    /**
     * Handle HTTP response
     */
    protected function handleResponse($response): array
    {
        try {
            $data = $response->json();
        } catch (\Exception $e) {
            Log::error("[Freemopay] Non-JSON response: " . substr($response->body(), 0, 200));
            throw new \Exception("Invalid API response (not JSON)");
        }

        if ($response->failed()) {
            Log::error("[Freemopay] API Error {$response->status()}: " . json_encode($data));

            $errorMessage = 'Unknown error';
            if (isset($data['message'])) {
                if (is_array($data['message'])) {
                    $errorMessage = $data['message']['fr'] ?? $data['message']['en'] ?? json_encode($data['message']);
                } else {
                    $errorMessage = $data['message'];
                }
            }

            throw new \Exception("API error: {$response->status()} - {$errorMessage}");
        }

        return $data;
    }
}
