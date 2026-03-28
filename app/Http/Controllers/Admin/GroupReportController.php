<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupReportController extends Controller
{
    /**
     * Afficher la liste des signalements
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'all');
        $search = $request->get('search');

        $query = GroupReport::with(['group', 'reporter', 'reviewer'])
            ->orderBy('created_at', 'desc');

        // Filtrer par statut
        if ($status !== 'all') {
            if ($status === 'pending') {
                $query->pending();
            } else {
                $query->where('status', $status);
            }
        }

        // Recherche
        if ($search) {
            $query->whereHas('group', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        $reports = $query->paginate(20);

        // Compter les signalements en attente
        $pendingCount = GroupReport::pending()->count();

        return view('admin.group-reports.index', compact('reports', 'status', 'search', 'pendingCount'));
    }

    /**
     * Afficher les détails d'un signalement
     */
    public function show(GroupReport $report)
    {
        $report->load(['group.creator', 'reporter', 'reviewer']);

        // Compter le nombre de signalements pour ce groupe
        $groupReportsCount = GroupReport::where('group_id', $report->group_id)->count();

        return view('admin.group-reports.show', compact('report', 'groupReportsCount'));
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
