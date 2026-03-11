<?php

namespace App\Services\Payment;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FreemopayTokenManager
{
    protected FreemopayClient $client;
    protected array $config;

    public function __construct(FreemopayClient $client)
    {
        $this->client = $client;
        $this->config = $this->loadConfig();
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
            'token_timeout' => Setting::get('freemopay_token_timeout', 30),
            'token_cache_duration' => Setting::get('freemopay_token_cache_duration', 3000),
        ];
    }

    /**
     * Get access token (from cache or generate new one)
     */
    public function getToken(): string
    {
        if (!$this->config['app_key'] || !$this->config['secret_key']) {
            throw new \Exception('Freemopay service is not configured properly');
        }

        $cacheKey = 'freemopay_access_token';

        $token = Cache::get($cacheKey);

        if ($token) {
            Log::debug("[Freemopay TokenManager] Using cached token");
            return $token;
        }

        Log::info("[Freemopay TokenManager] Generating new access token");
        $token = $this->generateToken();

        $cacheDuration = $this->config['token_cache_duration'];
        Cache::put($cacheKey, $token, $cacheDuration);

        Log::info("[Freemopay TokenManager] Token cached for {$cacheDuration} seconds");

        return $token;
    }

    /**
     * Generate a new access token from Freemopay API v2
     */
    protected function generateToken(): string
    {
        try {
            $baseUrl = rtrim($this->config['base_url'], '/');
            $url = $baseUrl . '/api/v2/payment/token';

            $payload = [
                'appKey' => $this->config['app_key'],
                'secretKey' => $this->config['secret_key'],
            ];

            Log::info("[Freemopay TokenManager] Requesting new token from: {$url}");

            $response = $this->client->post(
                $url,
                $payload,
                null,
                false,
                $this->config['token_timeout']
            );

            $token = $response['access_token'] ?? $response['token'] ?? $response['data']['token'] ?? null;

            if (!$token) {
                Log::error("[Freemopay TokenManager] No token in response: " . json_encode($response));
                throw new \Exception('No token in response');
            }

            Log::info("[Freemopay TokenManager] Token generated successfully");

            return $token;

        } catch (\Exception $e) {
            Log::error("[Freemopay TokenManager] Token generation failed: " . $e->getMessage());
            throw new \Exception("Failed to generate access token: {$e->getMessage()}");
        }
    }

    /**
     * Clear cached token
     */
    public function clearToken(): void
    {
        Cache::forget('freemopay_access_token');
        Log::info("[Freemopay TokenManager] Token cache cleared");
    }

    /**
     * Refresh token
     */
    public function refreshToken(): string
    {
        $this->clearToken();
        return $this->getToken();
    }
}
