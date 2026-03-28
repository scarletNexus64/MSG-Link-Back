@extends('admin.layouts.app')

@section('title', 'Détails du signalement')
@section('header', 'Détails du signalement')

@section('content')
<div class="space-y-6">
    <!-- Retour -->
    <div>
        <a href="{{ route('admin.group-reports.index') }}" class="text-primary-600 hover:text-primary-800">
            <i class="fas fa-arrow-left mr-2"></i>Retour à la liste
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Détails du signalement -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Informations du groupe -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-users mr-2 text-primary-500"></i>Groupe signalé
                </h3>
                @if($report->group)
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Nom du groupe</p>
                                <p class="text-base font-medium text-gray-900">{{ $report->group->name }}</p>
                            </div>
                            <a href="{{ route('admin.groups.show', $report->group) }}" class="text-primary-600 hover:text-primary-800">
                                <i class="fas fa-external-link-alt"></i> Voir
                            </a>
                        </div>
                        @if($report->group->description)
                            <div>
                                <p class="text-sm text-gray-500">Description</p>
                                <p class="text-base text-gray-900">{{ $report->group->description }}</p>
                            </div>
                        @endif
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Créateur</p>
                                <p class="text-base text-gray-900">{{ $report->group->creator->username ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Membres</p>
                                <p class="text-base text-gray-900">{{ $report->group->members_count }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Messages</p>
                                <p class="text-base text-gray-900">{{ $report->group->messages_count }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Créé le</p>
                                <p class="text-base text-gray-900">{{ $report->group->created_at->format('d/m/Y') }}</p>
                            </div>
                        </div>
                        @if($groupReportsCount > 1)
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Ce groupe a été signalé <strong>{{ $groupReportsCount }} fois</strong>
                                </p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-sm text-red-800">
                            <i class="fas fa-trash mr-2"></i>
                            Ce groupe a déjà été supprimé
                        </p>
                    </div>
                @endif
            </div>

            <!-- Détails du signalement -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-flag mr-2 text-red-500"></i>Détails du signalement
                </h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Raison</p>
                        <p class="text-base font-medium text-gray-900">
                            {{ \App\Models\GroupReport::getReasons()[$report->reason] ?? $report->reason }}
                        </p>
                    </div>
                    @if($report->description)
                        <div>
                            <p class="text-sm text-gray-500">Description</p>
                            <div class="mt-1 p-3 bg-gray-50 rounded-lg">
                                <p class="text-base text-gray-900">{{ $report->description }}</p>
                            </div>
                        </div>
                    @endif
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Signalé par</p>
                            @if($report->reporter)
                                <p class="text-base text-gray-900">{{ $report->reporter->username }}</p>
                                <p class="text-sm text-gray-500">{{ $report->reporter->email }}</p>
                            @else
                                <p class="text-base text-gray-500 italic">Utilisateur supprimé</p>
                            @endif
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Date du signalement</p>
                            <p class="text-base text-gray-900">{{ $report->created_at->format('d/m/Y à H:i') }}</p>
                            <p class="text-sm text-gray-500">{{ $report->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Traitement du signalement -->
            @if($report->status !== 'pending')
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <i class="fas fa-clipboard-check mr-2 text-green-500"></i>Traitement
                    </h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-500">Statut</p>
                            @if($report->status === 'reviewed')
                                <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                    En cours de traitement
                                </span>
                            @elseif($report->status === 'resolved')
                                <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                    Résolu
                                </span>
                            @else
                                <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                                    Rejeté
                                </span>
                            @endif
                        </div>
                        @if($report->reviewer)
                            <div>
                                <p class="text-sm text-gray-500">Traité par</p>
                                <p class="text-base text-gray-900">{{ $report->reviewer->first_name }} {{ $report->reviewer->last_name }}</p>
                            </div>
                        @endif
                        @if($report->reviewed_at)
                            <div>
                                <p class="text-sm text-gray-500">Date de traitement</p>
                                <p class="text-base text-gray-900">{{ $report->reviewed_at->format('d/m/Y à H:i') }}</p>
                            </div>
                        @endif
                        @if($report->admin_notes)
                            <div>
                                <p class="text-sm text-gray-500">Notes de l'administrateur</p>
                                <div class="mt-1 p-3 bg-gray-50 rounded-lg">
                                    <p class="text-base text-gray-900">{{ $report->admin_notes }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Actions -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow p-6 sticky top-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Actions</h3>

                @if($report->status === 'pending' && $report->group)
                    <!-- Mettre à jour le statut -->
                    <form method="POST" action="{{ route('admin.group-reports.update-status', $report) }}" class="space-y-4 mb-6">
                        @csrf
                        @method('PUT')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Changer le statut</label>
                            <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent">
                                <option value="reviewed">En cours</option>
                                <option value="resolved">Résolu</option>
                                <option value="dismissed">Rejeté</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optionnel)</label>
                            <textarea name="admin_notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-check mr-2"></i>Mettre à jour
                        </button>
                    </form>

                    <!-- Fermer le groupe -->
                    <div class="pt-6 border-t border-gray-200">
                        <button onclick="confirmCloseGroup()" class="w-full bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-ban mr-2"></i>Fermer le groupe
                        </button>
                        <p class="text-xs text-gray-500 mt-2 text-center">Cette action est irréversible</p>
                    </div>

                    <!-- Modal de confirmation -->
                    <div id="closeGroupModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                        <div class="bg-white rounded-lg max-w-md w-full p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Confirmer la fermeture
                            </h3>
                            <p class="text-gray-700 mb-4">
                                Êtes-vous sûr de vouloir fermer et supprimer définitivement ce groupe ?
                                Cette action est <strong>irréversible</strong>.
                            </p>
                            <form method="POST" action="{{ route('admin.group-reports.close-group', $report) }}">
                                @csrf
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Notes (optionnel)</label>
                                    <textarea name="admin_notes" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                                </div>
                                <div class="flex gap-3">
                                    <button type="button" onclick="closeModal()" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors">
                                        Annuler
                                    </button>
                                    <button type="submit" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                        Confirmer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @elseif(!$report->group)
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                        <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-600">Le groupe a déjà été supprimé</p>
                    </div>
                @else
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                        <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                        <p class="text-sm text-green-700">Ce signalement a été traité</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function confirmCloseGroup() {
    document.getElementById('closeGroupModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('closeGroupModal').classList.add('hidden');
}
</script>
@endpush
@endsection
