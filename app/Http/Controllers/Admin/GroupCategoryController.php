<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GroupCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupCategoryController extends Controller
{
    public function index()
    {
        $categories = GroupCategory::withCount('groups')->ordered()->get();
        return view('admin.group-categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.group-categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:group_categories,name',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // Generate slug from name
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        GroupCategory::create($validated);

        return redirect()->route('admin.group-categories.index')
            ->with('success', 'Catégorie créée avec succès');
    }

    public function edit(GroupCategory $groupCategory)
    {
        return view('admin.group-categories.edit', compact('groupCategory'));
    }

    public function update(Request $request, GroupCategory $groupCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:group_categories,name,' . $groupCategory->id,
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ]);

        // Update slug from name
        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->has('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? $groupCategory->sort_order;

        $groupCategory->update($validated);

        return redirect()->route('admin.group-categories.index')
            ->with('success', 'Catégorie mise à jour avec succès');
    }

    public function destroy(GroupCategory $groupCategory)
    {
        // Check if category has groups
        if ($groupCategory->groups()->count() > 0) {
            return redirect()->route('admin.group-categories.index')
                ->with('error', 'Impossible de supprimer cette catégorie car elle contient des groupes');
        }

        $groupCategory->delete();

        return redirect()->route('admin.group-categories.index')
            ->with('success', 'Catégorie supprimée avec succès');
    }
}
