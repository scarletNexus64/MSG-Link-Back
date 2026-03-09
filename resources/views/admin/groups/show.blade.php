@extends('admin.layouts.app')

@section('title', 'Détails du Groupe')
@section('header', 'Détails du Groupe')

@section('content')
<!-- Back Button -->
<div class="mb-6">
    <a href="{{ route('admin.groups.index') }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-900">
        <i class="fas fa-arrow-left mr-2"></i>
        Retour aux groupes
    </a>
</div>

<!-- Group Info Card -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
    <div class="flex items-start justify-between mb-6">
        <div class="flex items-start space-x-4">
            <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center text-white text-2xl font-bold">
                {{ strtoupper(substr($group->name, 0, 2)) }}
            </div>
            <div>
                <h2 class="text-2xl font-bold text-gray-900">{{ $group->name }}</h2>
                <p class="text-gray-500 mt-1">{{ $group->description ?? 'Aucune description' }}</p>
                <div class="flex items-center gap-4 mt-3">
                    @if($group->is_public)
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                            <i class="fas fa-globe mr-1"></i>Public
                        </span>
                    @else
                        <span class="px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">
                            <i class="fas fa-lock mr-1"></i>Privé
                        </span>
                    @endif
                    @if(!$group->is_public)
                        @if($group->is_discoverable)
                            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
                                <i class="fas fa-eye mr-1"></i>Découvrable
                            </span>
                        @else
                            <span class="px-3 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
                                <i class="fas fa-eye-slash mr-1"></i>Non découvrable
                            </span>
                        @endif
                    @endif
                    @if($group->category)
                        <span class="px-3 py-1 rounded-full text-xs font-medium"
                              style="background-color: {{ $group->category->color }}15; color: {{ $group->category->color }};">
                            <i class="fas fa-tag mr-1"></i>{{ $group->category->name }}
                        </span>
                    @else
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                            <i class="fas fa-tag mr-1"></i>Sans catégorie
                        </span>
                    @endif
                    <span class="text-sm text-gray-500">
                        <i class="fas fa-calendar mr-1"></i>
                        Créé le {{ $group->created_at->format('d/m/Y à H:i') }}
                    </span>
                </div>
            </div>
        </div>
        <form action="{{ route('admin.groups.destroy', $group) }}" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce groupe ?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                <i class="fas fa-trash mr-2"></i>Supprimer
            </button>
        </form>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-users text-lg text-purple-600"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Membres</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($stats['members']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-comments text-lg text-blue-600"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Messages</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($stats['messages']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-message text-lg text-green-600"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Aujourd'hui</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($stats['messages_today']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 rounded-lg p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-user-shield text-lg text-yellow-600"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Limite</p>
                    <p class="text-xl font-bold text-gray-900">{{ number_format($group->max_members) }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Column - Group Details -->
    <div class="lg:col-span-1 space-y-6">
        <!-- Creator Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Créateur</h3>
            <div class="flex items-center">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white font-semibold flex-shrink-0">
                    {{ strtoupper(substr($group->creator->first_name ?? 'U', 0, 1)) }}
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900">{{ $group->creator->first_name }} {{ $group->creator->last_name }}</p>
                    <p class="text-sm text-gray-500">{{ '@' . $group->creator->username }}</p>
                    <p class="text-xs text-gray-400">{{ $group->creator->email }}</p>
                </div>
            </div>
        </div>

        <!-- Invite Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Invitation</h3>
            <div class="space-y-3">
                <div>
                    <label class="text-xs text-gray-500 uppercase tracking-wider">Code d'invitation</label>
                    <div class="mt-1 flex items-center gap-2">
                        <code class="flex-1 px-3 py-2 bg-gray-100 rounded-lg text-sm font-mono">{{ $group->invite_code }}</code>
                        <button onclick="navigator.clipboard.writeText('{{ $group->invite_code }}')"
                                class="px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-500 uppercase tracking-wider">Lien d'invitation</label>
                    <div class="mt-1 flex items-center gap-2">
                        <input type="text"
                               value="{{ $group->invite_link }}"
                               readonly
                               class="flex-1 px-3 py-2 bg-gray-100 rounded-lg text-xs">
                        <button onclick="navigator.clipboard.writeText('{{ $group->invite_link }}')"
                                class="px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition-colors">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Activité</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-600">Dernière activité:</span>
                    <span class="font-medium text-gray-900">
                        @if($stats['last_activity'])
                            {{ $stats['last_activity']->diffForHumans() }}
                        @else
                            <span class="text-gray-400">Aucune</span>
                        @endif
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Créé il y a:</span>
                    <span class="font-medium text-gray-900">{{ $group->created_at->diffForHumans() }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Mis à jour:</span>
                    <span class="font-medium text-gray-900">{{ $group->updated_at->diffForHumans() }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column - Members List -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Membres ({{ $group->members_count }})</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Membre</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejoint le</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dernière lecture</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($group->activeMembers as $member)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white font-semibold flex-shrink-0">
                                            {{ $member->user->initial ?? '?' }}
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-gray-900">{{ $member->user->first_name }} {{ $member->user->last_name }}</p>
                                            <p class="text-xs text-gray-500">{{ '@' . $member->user->username }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($member->role === 'admin')
                                        <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">
                                            <i class="fas fa-crown mr-1"></i>Admin
                                        </span>
                                    @elseif($member->role === 'moderator')
                                        <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-medium">
                                            <i class="fas fa-shield-alt mr-1"></i>Modérateur
                                        </span>
                                    @else
                                        <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-full text-xs font-medium">
                                            <i class="fas fa-user mr-1"></i>Membre
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $member->joined_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    @if($member->last_read_at)
                                        {{ $member->last_read_at->diffForHumans() }}
                                    @else
                                        <span class="text-gray-400">Jamais</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($member->is_muted)
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs font-medium">
                                            <i class="fas fa-volume-mute mr-1"></i>En sourdine
                                        </span>
                                    @else
                                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                                            <i class="fas fa-check mr-1"></i>Actif
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <i class="fas fa-users text-4xl text-gray-300 mb-3"></i>
                                    <p class="text-gray-500">Aucun membre</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
