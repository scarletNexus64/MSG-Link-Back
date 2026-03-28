@extends('admin.layouts.app')

@section('title', 'Plaintes et Signalements')
@section('header', 'Plaintes et Signalements')

@section('content')
<div class="space-y-6">
    <!-- Header avec filtres -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <!-- Recherche -->
            <form method="GET" class="flex-1">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Rechercher un groupe..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </form>

            <!-- Filtres de statut -->
            <div class="flex gap-2">
                <a href="{{ route('admin.group-reports.index', ['status' => 'all', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'all' ? 'bg-primary-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Tous
                </a>
                <a href="{{ route('admin.group-reports.index', ['status' => 'pending', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    En attente
                    @if($pendingCount > 0)
                        <span class="ml-1 bg-white text-yellow-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ $pendingCount }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.group-reports.index', ['status' => 'reviewed', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'reviewed' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    En cours
                </a>
                <a href="{{ route('admin.group-reports.index', ['status' => 'resolved', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'resolved' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Résolus
                </a>
                <a href="{{ route('admin.group-reports.index', ['status' => 'dismissed', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'dismissed' ? 'bg-gray-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Rejetés
                </a>
            </div>
        </div>
    </div>

    <!-- Liste des signalements -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        @if($reports->count() > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Groupe</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Raison</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Signalé par</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($reports as $report)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($report->group)
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-primary-100 flex items-center justify-center">
                                                <i class="fas fa-users text-primary-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">{{ Str::limit($report->group->name, 30) }}</div>
                                                <div class="text-sm text-gray-500">{{ $report->group->members_count }} membres</div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-sm text-gray-500 italic">Groupe supprimé</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">{{ \App\Models\GroupReport::getReasons()[$report->reason] ?? $report->reason }}</div>
                                    @if($report->description)
                                        <div class="text-sm text-gray-500">{{ Str::limit($report->description, 50) }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($report->reporter)
                                        <div class="text-sm text-gray-900">{{ $report->reporter->username }}</div>
                                        <div class="text-sm text-gray-500">{{ $report->reporter->email }}</div>
                                    @else
                                        <span class="text-sm text-gray-500 italic">Utilisateur supprimé</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $report->created_at->format('d/m/Y') }}</div>
                                    <div class="text-sm text-gray-500">{{ $report->created_at->format('H:i') }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($report->status === 'pending')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            En attente
                                        </span>
                                    @elseif($report->status === 'reviewed')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            En cours
                                        </span>
                                    @elseif($report->status === 'resolved')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Résolu
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                            Rejeté
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('admin.group-reports.show', $report) }}"
                                       class="text-primary-600 hover:text-primary-900">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $reports->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <i class="fas fa-flag text-gray-400 text-5xl mb-4"></i>
                <p class="text-gray-500 text-lg">Aucun signalement trouvé</p>
            </div>
        @endif
    </div>
</div>
@endsection
