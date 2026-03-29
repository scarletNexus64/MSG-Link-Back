<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupReport;
use App\Models\Report;
use App\Models\Confession;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class GroupReportController extends Controller
{
    /**
     * Afficher la liste de TOUS les signalements (groupes + confessions + autres)
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');
        $type = $request->get('type', 'all'); // 'all', 'groups', 'confessions'
        $search = $request->get('search');

        // Récupérer les signalements de groupes
        $groupReportsQuery = GroupReport::with(['group', 'reporter', 'reviewer'])
            ->orderBy('created_at', 'desc');

        // Récupérer les signalements polymorphiques (confessions, etc.)
        $polymorphicReportsQuery = Report::with(['reportable', 'reporter', 'reviewer'])
            ->orderBy('created_at', 'desc');

        // Filtrer par statut
        if ($status !== 'all') {
            if ($status === 'pending') {
                $groupReportsQuery->pending();
                $polymorphicReportsQuery->pending();
            } else {
                $groupReportsQuery->where('status', $status);
                $polymorphicReportsQuery->where('status', $status);
            }
        }

        // Filtrer par type
        if ($type === 'groups') {
            $polymorphicReportsQuery->whereRaw('1 = 0'); // Ne pas inclure les reports polymorphiques
        } elseif ($type === 'confessions') {
            $groupReportsQuery->whereRaw('1 = 0'); // Ne pas inclure les group reports
            $polymorphicReportsQuery->where('reportable_type', Confession::class);
        } elseif ($type === 'other') {
            $groupReportsQuery->whereRaw('1 = 0');
            $polymorphicReportsQuery->where('reportable_type', '!=', Confession::class);
        }

        // Recherche
        if ($search) {
            $groupReportsQuery->whereHas('group', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });

            $polymorphicReportsQuery->where(function ($q) use ($search) {
                $q->whereHas('reportable', function ($subQ) use ($search) {
                    // Recherche dans le contenu des confessions
                    $subQ->where('content', 'like', "%{$search}%");
                })->orWhereHas('reporter', function ($subQ) use ($search) {
                    $subQ->where('username', 'like', "%{$search}%");
                });
            });
        }

        // Récupérer les signalements
        $groupReports = $groupReportsQuery->get();
        $polymorphicReports = $polymorphicReportsQuery->get();

        // Fusionner et trier par date
        $allReports = $groupReports->merge($polymorphicReports)->sortByDesc('created_at');

        // Paginer manuellement
        $currentPage = $request->get('page', 1);
        $perPage = 20;
        $reports = new \Illuminate\Pagination\LengthAwarePaginator(
            $allReports->forPage($currentPage, $perPage),
            $allReports->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // Compter les signalements en attente
        $pendingCount = GroupReport::pending()->count() + Report::pending()->count();
        $groupsPendingCount = GroupReport::pending()->count();
        $confessionsPendingCount = Report::pending()->where('reportable_type', Confession::class)->count();

        return view('admin.group-reports.index', compact(
            'reports',
            'status',
            'type',
            'search',
            'pendingCount',
            'groupsPendingCount',
            'confessionsPendingCount'
        ));
    }

    /**
     * Afficher les détails d'un signalement de groupe
     */
    public function show(GroupReport $report)
    {
        $report->load(['group.creator', 'reporter', 'reviewer']);

        // Compter le nombre de signalements pour ce groupe
        $groupReportsCount = GroupReport::where('group_id', $report->group_id)->count();

        return view('admin.group-reports.show', compact('report', 'groupReportsCount'));
    }

    /**
     * Afficher les détails d'un signalement de confession
     */
    public function showConfessionReport(Report $report)
    {
        $report->load(['reportable.author', 'reporter', 'reviewer']);

        // Vérifier que c'est bien un signalement de confession
        if ($report->reportable_type !== Confession::class) {
            abort(404, 'Ce signalement n\'est pas une confession');
        }

        // Compter le nombre de signalements pour cette confession
        $confessionReportsCount = Report::where('reportable_type', Confession::class)
            ->where('reportable_id', $report->reportable_id)
            ->count();

        return view('admin.group-reports.show-confession', compact('report', 'confessionReportsCount'));
    }

    /**
     * Mettre à jour le statut d'un signalement de confession
     */
    public function updateConfessionReportStatus(Request $request, Report $report)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                Report::STATUS_REVIEWED,
                Report::STATUS_RESOLVED,
                Report::STATUS_DISMISSED,
            ])],
            'action_taken' => 'nullable|string|max:1000',
        ]);

        if ($validated['status'] === Report::STATUS_RESOLVED) {
            $report->resolve($request->user(), $validated['action_taken'] ?? null);
        } elseif ($validated['status'] === Report::STATUS_DISMISSED) {
            $report->dismiss($request->user(), $validated['action_taken'] ?? null);
        } else {
            $report->markAsReviewed($request->user());
        }

        return redirect()->route('admin.group-reports.show-confession', $report)
            ->with('success', 'Statut du signalement mis à jour avec succès.');
    }

    /**
     * Supprimer une confession signalée
     */
    public function deleteConfession(Request $request, Report $report)
    {
        $validated = $request->validate([
            'action_taken' => 'nullable|string|max:1000',
        ]);

        $confession = $report->reportable;

        if (!$confession || $report->reportable_type !== Confession::class) {
            return redirect()->back()->with('error', 'La confession n\'existe plus.');
        }

        // Supprimer la confession
        $confessionId = $confession->id;
        $confession->delete();

        // Marquer le signalement comme résolu
        $report->resolve(
            $request->user(),
            $validated['action_taken'] ?? "Confession #{$confessionId} supprimée."
        );

        return redirect()->route('admin.group-reports.index')
            ->with('success', "La confession a été supprimée avec succès.");
    }

    /**
     * Mettre à jour le statut d'un signalement
     */
    public function updateStatus(Request $request, GroupReport $report)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                GroupReport::STATUS_REVIEWED,
                GroupReport::STATUS_RESOLVED,
                GroupReport::STATUS_DISMISSED,
            ])],
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $report->markAsReviewed(
            $request->user(),
            $validated['status'],
            $validated['admin_notes'] ?? null
        );

        return redirect()->route('admin.group-reports.show', $report)
            ->with('success', 'Statut du signalement mis à jour avec succès.');
    }

    /**
     * Fermer (supprimer) un groupe signalé
     */
    public function closeGroup(Request $request, GroupReport $report)
    {
        $validated = $request->validate([
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        $group = $report->group;

        if (!$group) {
            return redirect()->back()->with('error', 'Le groupe n\'existe plus.');
        }

        // Supprimer le groupe
        $groupName = $group->name;
        $group->delete();

        // Marquer le signalement comme résolu
        $report->markAsReviewed(
            $request->user(),
            GroupReport::STATUS_RESOLVED,
            $validated['admin_notes'] ?? "Groupe '{$groupName}' supprimé."
        );

        return redirect()->route('admin.group-reports.index')
            ->with('success', "Le groupe '{$groupName}' a été supprimé avec succès.");
    }
}
