@extends('admin.layouts.app')

@section('title', 'Plaintes et Signalements')
@section('header', 'Plaintes et Signalements')

@section('content')
<div class="space-y-6">
    <!-- Header avec filtres -->
    <div class="bg-white rounded-lg shadow p-6">
        <!-- Filtres par type de contenu -->
        <div class="mb-4 flex gap-2">
            <a href="{{ route('admin.group-reports.index', ['type' => 'all', 'status' => $status, 'search' => $search]) }}"
               class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($type ?? 'all') === 'all' ? 'bg-purple-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                <i class="fas fa-th"></i> Tous
                @if($pendingCount > 0)
                    <span class="ml-1 bg-white text-purple-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ $pendingCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.group-reports.index', ['type' => 'groups', 'status' => $status, 'search' => $search]) }}"
               class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($type ?? 'all') === 'groups' ? 'bg-primary-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                <i class="fas fa-users"></i> Groupes
                @if(isset($groupsPendingCount) && $groupsPendingCount > 0)
                    <span class="ml-1 bg-white text-primary-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ $groupsPendingCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.group-reports.index', ['type' => 'confessions', 'status' => $status, 'search' => $search]) }}"
               class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($type ?? 'all') === 'confessions' ? 'bg-indigo-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                <i class="fas fa-comment-dots"></i> Confessions
                @if(isset($confessionsPendingCount) && $confessionsPendingCount > 0)
                    <span class="ml-1 bg-white text-indigo-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ $confessionsPendingCount }}</span>
                @endif
            </a>
        </div>

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <!-- Recherche -->
            <form method="GET" class="flex-1">
                <input type="hidden" name="status" value="{{ $status }}">
                <input type="hidden" name="type" value="{{ $type ?? 'all' }}">
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search ?? '' }}"
                        placeholder="Rechercher..."
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
                </div>
            </form>

            <!-- Filtres de statut -->
            <div class="flex gap-2">
                <a href="{{ route('admin.group-reports.index', ['status' => 'all', 'type' => $type ?? 'all', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'all' ? 'bg-primary-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Tous
                </a>
                <a href="{{ route('admin.group-reports.index', ['status' => 'pending', 'type' => $type ?? 'all', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    En attente
                    @if($pendingCount > 0)
                        <span class="ml-1 bg-white text-yellow-600 text-xs font-bold px-2 py-0.5 rounded-full">{{ $pendingCount }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.group-reports.index', ['status' => 'reviewed', 'type' => $type ?? 'all', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'reviewed' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    En cours
                </a>
                <a href="{{ route('admin.group-reports.index', ['status' => 'resolved', 'type' => $type ?? 'all', 'search' => $search]) }}"
                   class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ $status === 'resolved' ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Résolus
                </a>
                <a href="{{ route('admin.group-reports.index', ['status' => 'dismissed', 'type' => $type ?? 'all', 'search' => $search]) }}"
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contenu</th>
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
                                <!-- Type -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if(isset($report->group))
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-primary-100 text-primary-800">
                                            <i class="fas fa-users"></i> Groupe
                                        </span>
                                    @elseif(isset($report->reportable_type) && $report->reportable_type === 'App\Models\Confession')
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">
                                            <i class="fas fa-comment-dots"></i> Confession
                                        </span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                            {{ $report->reportable_type_label ?? 'Autre' }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Contenu -->
                                <td class="px-6 py-4">
                                    @if(isset($report->group))
                                        <!-- Groupe -->
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
                                    @elseif(isset($report->reportable))
                                        <!-- Confession ou autre -->
                                        @if($report->reportable_type === 'App\Models\Confession')
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10 rounded-lg bg-indigo-100 flex items-center justify-center">
                                                    <i class="fas fa-comment-dots text-indigo-600"></i>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">{{ Str::limit($report->reportable->content ?? 'Contenu supprimé', 50) }}</div>
                                                    <div class="text-sm text-gray-500">Par: {{ $report->reportable->author_initial ?? '?' }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-sm text-gray-500">{{ $report->reportable_type_label }}</span>
                                        @endif
                                    @else
                                        <span class="text-sm text-gray-500 italic">Contenu supprimé</span>
                                    @endif
                                </td>

                                <!-- Raison -->
                                <td class="px-6 py-4">
                                    @if(isset($report->group))
                                        <div class="text-sm text-gray-900">{{ \App\Models\GroupReport::getReasons()[$report->reason] ?? $report->reason }}</div>
                                    @else
                                        <div class="text-sm text-gray-900">{{ $report->reason_label ?? $report->reason }}</div>
                                    @endif
                                    @if($report->description)
                                        <div class="text-sm text-gray-500">{{ Str::limit($report->description, 50) }}</div>
                                    @endif
                                </td>

                                <!-- Signalé par -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($report->reporter)
                                        <div class="text-sm text-gray-900">{{ $report->reporter->username }}</div>
                                        <div class="text-sm text-gray-500">{{ $report->reporter->email }}</div>
                                    @else
                                        <span class="text-sm text-gray-500 italic">Utilisateur supprimé</span>
                                    @endif
                                </td>

                                <!-- Date -->
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $report->created_at->format('d/m/Y') }}</div>
                                    <div class="text-sm text-gray-500">{{ $report->created_at->format('H:i') }}</div>
                                </td>

                                <!-- Statut -->
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

                                <!-- Actions -->
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @if(isset($report->group))
                                        <a href="{{ route('admin.group-reports.show', $report) }}"
                                           class="text-primary-600 hover:text-primary-900">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    @else
                                        <a href="{{ route('admin.group-reports.show-confession', $report) }}"
                                           class="text-primary-600 hover:text-primary-900">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $reports->appends(request()->query())->links() }}
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
