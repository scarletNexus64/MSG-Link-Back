<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Payment\FreemopayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FreemopaySettingsController extends Controller
{
    /**
     * Show Freemopay configuration page
     */
    public function index()
    {
        return view('admin.settings.freemopay');
    }

    /**
     * Update Freemopay configuration
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'freemopay_base_url' => 'required|url',
            'freemopay_app_key' => 'required|string|min:5',
            'freemopay_secret_key' => 'required|string|min:5',
            'freemopay_callback_url' => 'required|url',
            'freemopay_init_payment_timeout' => 'required|integer|min:1|max:120',
            'freemopay_status_check_timeout' => 'required|integer|min:1|max:90',
            'freemopay_token_timeout' => 'required|integer|min:1|max:60',
            'freemopay_token_cache_duration' => 'required|integer|min:60|max:3600',
            'freemopay_max_retries' => 'required|integer|min:0|max:5',
            'freemopay_retry_delay' => 'required|numeric|min:0|max:5',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Erreurs de validation: ' . implode(' | ', $validator->errors()->all()));
        }

        try {
            // Save all settings
            Setting::set('freemopay_base_url', $request->freemopay_base_url);
            Setting::set('freemopay_app_key', $request->freemopay_app_key);
            Setting::set('freemopay_secret_key', $request->freemopay_secret_key);
            Setting::set('freemopay_callback_url', $request->freemopay_callback_url);
            Setting::set('freemopay_init_payment_timeout', $request->freemopay_init_payment_timeout);
            Setting::set('freemopay_status_check_timeout', $request->freemopay_status_check_timeout);
            Setting::set('freemopay_token_timeout', $request->freemopay_token_timeout);
            Setting::set('freemopay_token_cache_duration', $request->freemopay_token_cache_duration);
            Setting::set('freemopay_max_retries', $request->freemopay_max_retries);
            Setting::set('freemopay_retry_delay', $request->freemopay_retry_delay);
            Setting::set('freemopay_active', $request->has('freemopay_active') ? 1 : 0);

            return redirect()->back()
                ->with('success', 'Configuration Freemopay mise à jour avec succès!');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Erreur lors de la mise à jour: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Test Freemopay connection
     */
    public function test()
    {
        try {
            $service = new FreemopayService();

            if (!$service->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration Freemopay invalide ou incomplète'
                ], 400);
            }

            $result = $service->testConnection();

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}
