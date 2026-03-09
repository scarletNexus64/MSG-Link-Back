@extends('admin.layouts.app')

@section('title', 'Groupes')
@section('header', 'Gestion des Groupes')

@section('content')
<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Total</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ number_format($stats['total']) }}</p>
            </div>
            <div class="w-12 h-12 bg-indigo-100 rounded-full flex items-center justify-center">
                <i class="fas fa-users-between-lines text-xl text-indigo-600"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Publics</p>
                <p class="text-3xl font-bold text-green-600 mt-1">{{ number_format($stats['public']) }}</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fas fa-globe text-xl text-green-600"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Privés</p>
                <p class="text-3xl font-bold text-orange-600 mt-1">{{ number_format($stats['private']) }}</p>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                <i class="fas fa-lock text-xl text-orange-600"></i>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Aujourd'hui</p>
                <p class="text-3xl font-bold text-blue-600 mt-1">{{ number_format($stats['today']) }}</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fas fa-calendar-day text-xl text-blue-600"></i>
            </div>
        </div>
    </div>
</div>

<!-- Additional Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-users text-lg text-purple-600"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Total Membres</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_members']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-pink-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-comments text-lg text-pink-600"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Total Messages</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_messages']) }}</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                <i class="fas fa-chart-line text-lg text-yellow-600"></i>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-500">Cette semaine</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['this_week']) }}</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-8">
    <form method="GET" action="{{ route('admin.groups.index') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Rechercher</label>
            <input type="text"
                   name="search"
                   value="{{ request('search') }}"
                   placeholder="Nom du groupe, description, créateur..."
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Visibilité</label>
            <select name="is_public" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                <option value="">Tous</option>
                <option value="1" {{ request('is_public') === '1' ? 'selected' : '' }}>Public</option>
                <option value="0" {{ request('is_public') === '0' ? 'selected' : '' }}>Privé</option>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition-colors">
                <i class="fas fa-search mr-2"></i>Filtrer
            </button>
            @if(request()->hasAny(['search', 'is_public']))
                <a href="{{ route('admin.groups.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="fas fa-times"></i>
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Groups List -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Groupe</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Créateur</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Membres</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Messages</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visibilité</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catégorie</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Créé le</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($groups as $group)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $group->name }}</p>
                                @if($group->description)
                                    <p class="text-sm text-gray-500 truncate max-w-xs">{{ $group->description }}</p>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 font-semibold flex-shrink-0">
                                    {{ strtoupper(substr($group->creator?->first_name ?? 'U', 0, 1)) }}
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm font-medium text-gray-900">{{ '@' . ($group->creator?->username ?? 'unknown') }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center text-sm text-gray-900">
                                <i class="fas fa-users text-gray-400 mr-2"></i>
                                {{ number_format($group->members_count) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center text-sm text-gray-900">
                                <i class="fas fa-comment-dots text-gray-400 mr-2"></i>
                                {{ number_format($group->messages_count) }}
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($group->is_public)
                                <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                                    <i class="fas fa-globe mr-1"></i>Public
                                </span>
                            @else
                                <span class="px-2 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">
                                    <i class="fas fa-lock mr-1"></i>Privé
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($group->category)
                                <span class="px-2 py-1 rounded-full text-xs font-medium"
                                      style="background-color: {{ $group->category->color }}15; color: {{ $group->category->color }};">
                                    {{ $group->category->name }}
                                </span>
                            @else
                                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                                    Sans catégorie
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $group->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm space-x-2">
                            <a href="{{ route('admin.groups.show', $group) }}"
                               class="text-primary-600 hover:text-primary-800">
                                <i class="fas fa-eye"></i>
                            </a>
                            <form action="{{ route('admin.groups.destroy', $group) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce groupe ?')"
                                        class="text-red-600 hover:text-red-800">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <i class="fas fa-users-between-lines text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">Aucun groupe trouvé</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    @if($groups->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $groups->links() }}
        </div>
    @endif
</div>
@endsection
