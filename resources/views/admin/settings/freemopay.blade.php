@extends('admin.layouts.app')

@section('title', 'Configuration Freemopay')
@section('header', 'Configuration Freemopay')

@section('content')
<div class="max-w-4xl">
    <!-- Back button -->
    <div class="mb-6">
        <a href="{{ route('admin.settings') }}" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Retour aux paramètres
        </a>
    </div>

    <!-- Freemopay Configuration -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>
                Configuration Freemopay
            </h3>
        </div>
        <div class="p-6">
            <form action="{{ route('admin.settings.freemopay.update') }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Active checkbox -->
                <div class="mb-4">
                    <div class="flex items-center">
                        <input type="checkbox" id="freemopay_active" name="freemopay_active" value="1"
                               {{ old('freemopay_active', \App\Models\Setting::get('freemopay_active')) ? 'checked' : '' }}
                               class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                        <label for="freemopay_active" class="ml-2 text-sm font-medium text-gray-900">
                            Activer Freemopay
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-1 ml-6">Activer ce provider pour les paiements et retraits</p>
                </div>

                <div class="alert alert-info mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
                    <i class="fas fa-info-circle mr-2"></i>
                    <strong>Important:</strong> Freemopay utilise l'API v2 avec authentification Bearer Token.
                </div>

                <!-- Base URL -->
                <div class="mb-4">
                    <label for="freemopay_base_url" class="block text-sm font-medium text-gray-700 mb-2">
                        URL de base <span class="text-red-500">*</span>
                    </label>
                    <input type="url" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('freemopay_base_url') border-red-500 @enderror"
                           id="freemopay_base_url" name="freemopay_base_url"
                           value="{{ old('freemopay_base_url', \App\Models\Setting::get('freemopay_base_url', 'https://api-v2.freemopay.com')) }}"
                           placeholder="https://api-v2.freemopay.com" required>
                    @error('freemopay_base_url')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- App Key & Secret Key -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="freemopay_app_key" class="block text-sm font-medium text-gray-700 mb-2">
                            App Key <span class="text-red-500">*</span>
                        </label>
                        <input type="text" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('freemopay_app_key') border-red-500 @enderror"
                               id="freemopay_app_key" name="freemopay_app_key"
                               value="{{ old('freemopay_app_key', \App\Models\Setting::get('freemopay_app_key')) }}"
                               placeholder="app_xxxxxxxxxx" required>
                        @error('freemopay_app_key')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="freemopay_secret_key" class="block text-sm font-medium text-gray-700 mb-2">
                            Secret Key <span class="text-red-500">*</span>
                        </label>
                        <input type="password" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('freemopay_secret_key') border-red-500 @enderror"
                               id="freemopay_secret_key" name="freemopay_secret_key"
                               value="{{ old('freemopay_secret_key', \App\Models\Setting::get('freemopay_secret_key')) }}"
                               placeholder="secret_xxxxxxxxxx" required>
                        @error('freemopay_secret_key')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Callback URL -->
                <div class="mb-4">
                    <label for="freemopay_callback_url" class="block text-sm font-medium text-gray-700 mb-2">
                        Callback URL <span class="text-red-500">*</span>
                    </label>
                    <input type="url" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 @error('freemopay_callback_url') border-red-500 @enderror"
                           id="freemopay_callback_url" name="freemopay_callback_url"
                           value="{{ old('freemopay_callback_url', \App\Models\Setting::get('freemopay_callback_url', config('app.url') . '/api/webhooks/freemopay')) }}"
                           placeholder="https://votresite.com/api/webhooks/freemopay" required>
                    @error('freemopay_callback_url')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-gray-500 mt-1">URL publique pour recevoir les notifications de paiement</p>
                </div>

                <!-- Advanced Parameters -->
                <h5 class="text-md font-semibold text-gray-800 mt-6 mb-3">Paramètres avancés</h5>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="freemopay_init_payment_timeout" class="block text-sm font-medium text-gray-700 mb-2">
                            Timeout init paiement (s)
                        </label>
                        <input type="number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                               id="freemopay_init_payment_timeout" name="freemopay_init_payment_timeout"
                               value="{{ old('freemopay_init_payment_timeout', \App\Models\Setting::get('freemopay_init_payment_timeout', 60)) }}"
                               min="1" max="120" required>
                    </div>

                    <div>
                        <label for="freemopay_status_check_timeout" class="block text-sm font-medium text-gray-700 mb-2">
                            Timeout vérif statut (s)
                        </label>
                        <input type="number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                               id="freemopay_status_check_timeout" name="freemopay_status_check_timeout"
                               value="{{ old('freemopay_status_check_timeout', \App\Models\Setting::get('freemopay_status_check_timeout', 30)) }}"
                               min="1" max="90" required>
                    </div>

                    <div>
                        <label for="freemopay_token_timeout" class="block text-sm font-medium text-gray-700 mb-2">
                            Timeout token (s)
                        </label>
                        <input type="number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                               id="freemopay_token_timeout" name="freemopay_token_timeout"
                               value="{{ old('freemopay_token_timeout', \App\Models\Setting::get('freemopay_token_timeout', 30)) }}"
                               min="1" max="60" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div>
                        <label for="freemopay_token_cache_duration" class="block text-sm font-medium text-gray-700 mb-2">
                            Durée cache token (s)
                        </label>
                        <input type="number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                               id="freemopay_token_cache_duration" name="freemopay_token_cache_duration"
                               value="{{ old('freemopay_token_cache_duration', \App\Models\Setting::get('freemopay_token_cache_duration', 3000)) }}"
                               min="60" max="3600" required>
                        <p class="text-xs text-gray-500 mt-1">3000s = 50 min (token expire à 60 min)</p>
                    </div>

                    <div>
                        <label for="freemopay_max_retries" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre de tentatives
                        </label>
                        <input type="number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                               id="freemopay_max_retries" name="freemopay_max_retries"
                               value="{{ old('freemopay_max_retries', \App\Models\Setting::get('freemopay_max_retries', 2)) }}"
                               min="0" max="5" required>
                    </div>

                    <div>
                        <label for="freemopay_retry_delay" class="block text-sm font-medium text-gray-700 mb-2">
                            Délai entre tentatives (s)
                        </label>
                        <input type="number" step="0.1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                               id="freemopay_retry_delay" name="freemopay_retry_delay"
                               value="{{ old('freemopay_retry_delay', \App\Models\Setting::get('freemopay_retry_delay', 0.5)) }}"
                               min="0" max="5" required>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-save mr-2"></i>Sauvegarder
                    </button>
                    <button type="button" onclick="testFreemopayConnection()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-check-circle mr-2"></i>Tester la connexion
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Help Card -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-6">
        <h4 class="font-semibold text-blue-900 mb-3">
            <i class="fas fa-info-circle mr-2"></i>Aide Freemopay
        </h4>
        <div class="text-sm text-blue-800 space-y-2">
            <p><strong>Configuration requise:</strong></p>
            <ul class="list-disc list-inside ml-4">
                <li>Compte Freemopay Business</li>
                <li>App Key et Secret Key</li>
                <li>URL de callback publique (HTTPS recommandé)</li>
            </ul>

            <p class="mt-3"><strong>Où trouver vos credentials:</strong></p>
            <ol class="list-decimal list-inside ml-4">
                <li>Connectez-vous à votre <a href="https://business.freemopay.com" target="_blank" class="underline">compte Freemopay Business</a></li>
                <li>Accédez à "Paramètres API"</li>
                <li>Copiez votre App Key et Secret Key</li>
            </ol>

            <p class="mt-3"><strong>Callback URL:</strong></p>
            <p class="ml-4">Freemopay enverra les notifications de paiement à cette URL. Elle doit être accessible depuis Internet et en HTTPS (production).</p>
        </div>
    </div>
</div>

<script>
function testFreemopayConnection() {
    const button = event.target;
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Test en cours...';

    fetch('{{ route('admin.settings.freemopay.test') }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Connexion réussie!\n\n' + data.message);
        } else {
            alert('❌ Erreur de connexion\n\n' + data.message);
        }
    })
    .catch(error => {
        alert('❌ Erreur: ' + error.message);
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    });
}
</script>
@endsection
