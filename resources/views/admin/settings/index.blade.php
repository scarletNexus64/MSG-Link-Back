@extends('admin.layouts.app')

@section('title', 'Paramètres')
@section('header', 'Paramètres de configuration')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- En-tête avec description -->
    <div class="mb-6">
        <p class="text-gray-600">
            <i class="fas fa-info-circle mr-2"></i>
            Gérez tous les paramètres de votre application depuis cette interface. Les modifications sont appliquées immédiatement.
        </p>
    </div>

    <form action="{{ route('admin.settings.update') }}" method="POST">
        @csrf
        @method('PUT')

        <!-- Tabs Navigation -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6" x-data="{ activeTab: 'premium' }">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px overflow-x-auto" aria-label="Tabs">
                    <button type="button" @click="activeTab = 'premium'"
                            :class="activeTab === 'premium' ? 'border-yellow-500 text-yellow-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-crown mr-2"></i>Premium
                    </button>
                    <button type="button" @click="activeTab = 'wallet'"
                            :class="activeTab === 'wallet' ? 'border-green-500 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-wallet mr-2"></i>Portefeuille
                    </button>
                    <button type="button" @click="activeTab = 'gifts'"
                            :class="activeTab === 'gifts' ? 'border-pink-500 text-pink-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-gift mr-2"></i>Cadeaux
                    </button>
                    <button type="button" @click="activeTab = 'security'"
                            :class="activeTab === 'security' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-lock mr-2"></i>Sécurité
                    </button>
                    <button type="button" @click="activeTab = 'moderation'"
                            :class="activeTab === 'moderation' ? 'border-red-500 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-shield-alt mr-2"></i>Modération
                    </button>
                    <button type="button" @click="activeTab = 'advanced'"
                            :class="activeTab === 'advanced' ? 'border-gray-500 text-gray-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                            class="whitespace-nowrap py-4 px-6 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-cogs mr-2"></i>Avancé
                    </button>
                </nav>
            </div>

            <!-- Tab Panels -->
            <div class="p-6">
                <!-- Premium Settings -->
                <div x-show="activeTab === 'premium'" x-cloak>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($premiumSettings as $setting)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $setting->description }}
                            </label>
                            @if($setting->type === 'boolean')
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                       name="{{ $setting->key }}"
                                       value="1"
                                       {{ $setting->value == '1' ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-yellow-600 shadow-sm focus:border-yellow-300 focus:ring focus:ring-yellow-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-600">Activer cette fonctionnalité</span>
                            </label>
                            @else
                            <div class="relative">
                                <input type="{{ $setting->type === 'integer' || $setting->type === 'decimal' ? 'number' : 'text' }}"
                                       name="{{ $setting->key }}"
                                       value="{{ old($setting->key, $setting->value) }}"
                                       step="{{ $setting->type === 'decimal' ? '0.01' : '1' }}"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 transition-colors"
                                       placeholder="Entrez {{ strtolower($setting->description) }}">
                                @if(str_contains($setting->key, 'price'))
                                <span class="absolute right-3 top-2.5 text-gray-500 text-sm">FCFA</span>
                                @endif
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4 p-4 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                        <p class="text-sm text-yellow-700">
                            <i class="fas fa-crown mr-2"></i>
                            Configuration des fonctionnalités premium de votre application.
                        </p>
                    </div>
                </div>

                <!-- Wallet Settings -->
                <div x-show="activeTab === 'wallet'" x-cloak>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($walletSettings as $setting)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $setting->description }}
                            </label>
                            <div class="relative">
                                <input type="{{ $setting->type === 'integer' || $setting->type === 'decimal' ? 'number' : 'text' }}"
                                       name="{{ $setting->key }}"
                                       value="{{ old($setting->key, $setting->value) }}"
                                       step="{{ $setting->type === 'decimal' ? '0.01' : '1' }}"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors"
                                       placeholder="Entrez {{ strtolower($setting->description) }}">
                                @if(str_contains($setting->key, 'withdrawal') || str_contains($setting->key, 'fee'))
                                <span class="absolute right-3 top-2.5 text-gray-500 text-sm">FCFA</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4 p-4 bg-green-50 border-l-4 border-green-400 rounded">
                        <p class="text-sm text-green-700">
                            <i class="fas fa-wallet mr-2"></i>
                            Paramètres liés au portefeuille et aux retraits des utilisateurs.
                        </p>
                    </div>
                </div>

                <!-- Gifts Settings -->
                <div x-show="activeTab === 'gifts'" x-cloak>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($giftsSettings as $setting)
                            @if($setting->key === 'gifts_platform_fee_percent')
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ $setting->description }}
                                </label>
                                <div class="relative">
                                    <input type="{{ $setting->type === 'integer' || $setting->type === 'decimal' ? 'number' : 'text' }}"
                                           name="{{ $setting->key }}"
                                           value="{{ old($setting->key, $setting->value) }}"
                                           step="{{ $setting->type === 'decimal' ? '0.01' : '1' }}"
                                           min="0"
                                           max="100"
                                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500 transition-colors"
                                           placeholder="Entrez le pourcentage de commission">
                                    <span class="absolute right-3 top-2.5 text-gray-500 text-sm">%</span>
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="mt-4 p-4 bg-pink-50 border-l-4 border-pink-400 rounded">
                        <p class="text-sm text-pink-700">
                            <i class="fas fa-gift mr-2"></i>
                            Configuration de la commission de la plateforme sur les cadeaux virtuels.
                        </p>
                    </div>
                </div>

                <!-- Security Settings -->
                <div x-show="activeTab === 'security'" x-cloak>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($securitySettings as $setting)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $setting->description }}
                            </label>
                            <input type="number"
                                   name="{{ $setting->key }}"
                                   value="{{ old($setting->key, $setting->value) }}"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors"
                                   placeholder="Entrez {{ strtolower($setting->description) }}">
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4 p-4 bg-indigo-50 border-l-4 border-indigo-400 rounded">
                        <p class="text-sm text-indigo-700">
                            <i class="fas fa-lock mr-2"></i>
                            Configurez les paramètres de sécurité et d'authentification.
                        </p>
                    </div>
                </div>

                <!-- Moderation Settings -->
                <div x-show="activeTab === 'moderation'" x-cloak>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        @foreach($moderationSettings as $setting)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                {{ $setting->description }}
                            </label>
                            @if($setting->type === 'boolean')
                            <label class="inline-flex items-center cursor-pointer">
                                <input type="checkbox"
                                       name="{{ $setting->key }}"
                                       value="1"
                                       {{ $setting->value == '1' ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-red-600 shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-600">Activer cette fonctionnalité</span>
                            </label>
                            @else
                            <input type="number"
                                   name="{{ $setting->key }}"
                                   value="{{ old($setting->key, $setting->value) }}"
                                   class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors"
                                   placeholder="Entrez {{ strtolower($setting->description) }}">
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <div class="mt-4 p-4 bg-red-50 border-l-4 border-red-400 rounded">
                        <p class="text-sm text-red-700">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Contrôlez le contenu et la modération de votre plateforme.
                        </p>
                    </div>
                </div>

                <!-- Advanced Settings -->
                <div x-show="activeTab === 'advanced'" x-cloak>
                    <!-- Chat Settings -->
                    <div class="mb-8">
                        <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-comments text-purple-600 mr-2"></i>
                            Chat & Flammes
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($chatSettings as $setting)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ $setting->description }}
                                </label>
                                <input type="number"
                                       name="{{ $setting->key }}"
                                       value="{{ old($setting->key, $setting->value) }}"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Rate Limits -->
                    <div class="mb-8">
                        <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-tachometer-alt text-orange-600 mr-2"></i>
                            Limites de taux
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($rateLimitsSettings as $setting)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ $setting->description }}
                                </label>
                                <input type="number"
                                       name="{{ $setting->key }}"
                                       value="{{ old($setting->key, $setting->value) }}"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors">
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- General Settings -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-cog text-gray-600 mr-2"></i>
                            Paramètres généraux
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @foreach($generalSettings as $setting)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ $setting->description }}
                                </label>
                                <input type="{{ $setting->type === 'integer' || $setting->type === 'decimal' ? 'number' : 'text' }}"
                                       name="{{ $setting->key }}"
                                       value="{{ old($setting->key, $setting->value) }}"
                                       step="{{ $setting->type === 'decimal' ? '0.01' : '1' }}"
                                       class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-500 focus:border-gray-500 transition-colors">
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4 p-4 bg-gray-50 border-l-4 border-gray-400 rounded">
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-cogs mr-2"></i>
                            Paramètres avancés pour une configuration fine de l'application.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-between items-center bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex gap-3">
                <button type="button" onclick="event.preventDefault(); document.getElementById('clear-cache-form').submit();"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm">
                    <i class="fas fa-broom mr-2"></i>Vider le cache
                </button>
                <button type="button" onclick="event.preventDefault(); document.getElementById('clear-config-form').submit();"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm">
                    <i class="fas fa-sync mr-2"></i>Recharger la config
                </button>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('admin.dashboard') }}" class="px-6 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                    Annuler
                </a>
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium shadow-sm">
                    <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                </button>
            </div>
        </div>
    </form>

    <!-- Hidden forms for cache actions -->
    <form id="clear-cache-form" action="{{ route('admin.cache.clear') }}" method="POST" style="display: none;">
        @csrf
    </form>
    <form id="clear-config-form" action="{{ route('admin.cache.config') }}" method="POST" style="display: none;">
        @csrf
    </form>
</div>
@endsection
