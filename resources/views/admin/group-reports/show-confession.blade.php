@extends('admin.layouts.app')

@section('title', 'Détails du signalement - Confession')
@section('header', 'Détails du signalement - Confession')

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
            <!-- Informations de la confession -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    <i class="fas fa-comment-dots mr-2 text-indigo-500"></i>Confession signalée
                </h3>
                @if($report->reportable)
                    <div class="space-y-3">
                        <div>
                            <p class="text-sm text-gray-500">Contenu</p>
                            <div class="mt-1 p-4 bg-gray-50 rounded-lg">
                                <p class="text-base text-gray-900">{{ $report->reportable->content }}</p>
                            </div>
                        </div>

                        @if($report->reportable->media_type !== 'none' && $report->reportable->media_url)
                            <div>
                                <p class="text-sm text-gray-500">Média</p>
                                <div class="mt-1">
                                    @if($report->reportable->media_type === 'image')
                                        <img src="{{ url('storage/' . $report->reportable->media_url) }}" alt="Image" class="rounded-lg max-w-md">
                                    @elseif($report->reportable->media_type === 'video')
                                        <video controls class="rounded-lg max-w-md">
                                            <source src="{{ url('storage/' . $report->reportable->media_url) }}" type="video/mp4">
                                        </video>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Auteur</p>
                                @if($report->reportable->is_identity_revealed && $report->reportable->author)
                                    <p class="text-base text-gray-900">{{ $report->reportable->author->username }}</p>
                                @else
                                    <p class="text-base text-gray-900">Anonyme ({{ $report->reportable->author_initial }})</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Type</p>
                                <p class="text-base text-gray-900">
                                    @if($report->reportable->is_public)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Public</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">Privé</span>
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Likes</p>
                                <p class="text-base text-gray-900">{{ $report->reportable->likes_count ?? 0 }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Vues</p>
                                <p class="text-base text-gray-900">{{ $report->reportable->views_count ?? 0 }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Statut</p>
                                <p class="text-base text-gray-900">
                                    @if($report->reportable->is_approved)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Approuvée</span>
                                    @elseif($report->reportable->is_pending)
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">En attente</span>
                                    @else
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Rejetée</span>
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Créée le</p>
                                <p class="text-base text-gray-900">{{ $report->reportable->created_at->format('d/m/Y') }}</p>
                            </div>
                        </div>
                        @if($confessionReportsCount > 1)
                            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                <p class="text-sm text-yellow-800">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Cette confession a été signalée <strong>{{ $confessionReportsCount }} fois</strong>
                                </p>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-sm text-red-800">
                            <i class="fas fa-trash mr-2"></i>
                            Cette confession a déjà été supprimée
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
                            {{ $report->reason_label ?? $report->reason }}
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
                        @if($report->action_taken)
                            <div>
                                <p class="text-sm text-gray-500">Action prise</p>
                                <div class="mt-1 p-3 bg-gray-50 rounded-lg">
                                    <p class="text-base text-gray-900">{{ $report->action_taken }}</p>
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

                @if($report->status === 'pending' && $report->reportable)
                    <!-- Mettre à jour le statut -->
                    <form method="POST" action="{{ route('admin.group-reports.update-confession-status', $report) }}" class="space-y-4 mb-6">
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Action prise (optionnel)</label>
                            <textarea name="action_taken" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-transparent"></textarea>
                        </div>
                        <button type="submit" class="w-full bg-primary-500 hover:bg-primary-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-check mr-2"></i>Mettre à jour
                        </button>
                    </form>

                    <!-- Supprimer la confession -->
                    <div class="pt-6 border-t border-gray-200">
                        <button onclick="confirmDeleteConfession()" class="w-full bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-trash mr-2"></i>Supprimer la confession
                        </button>
                        <p class="text-xs text-gray-500 mt-2 text-center">Cette action est irréversible</p>
                    </div>

                    <!-- Modal de confirmation -->
                    <div id="deleteConfessionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
                        <div class="bg-white rounded-lg max-w-md w-full p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Confirmer la suppression
                            </h3>
                            <p class="text-gray-700 mb-4">
                                Êtes-vous sûr de vouloir supprimer définitivement cette confession ?
                                Cette action est <strong>irréversible</strong>.
                            </p>
                            <form method="POST" action="{{ route('admin.group-reports.delete-confession', $report) }}">
                                @csrf
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Action prise (optionnel)</label>
                                    <textarea name="action_taken" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
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
                @elseif(!$report->reportable)
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                        <i class="fas fa-info-circle text-gray-400 text-2xl mb-2"></i>
                        <p class="text-sm text-gray-600">La confession a déjà été supprimée</p>
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
function confirmDeleteConfession() {
    document.getElementById('deleteConfessionModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('deleteConfessionModal').classList.add('hidden');
}
</script>
@endpush
@endsection
