<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GroupCategory;
use Illuminate\Http\JsonResponse;

class GroupCategoryController extends Controller
{
    /**
     * Liste toutes les catégories actives
     */
    public function index(): JsonResponse
    {
        $categories = GroupCategory::active()
            ->ordered()
            ->get();

        return response()->json([
            'categories' => $categories,
        ]);
    }

    /**
     * Obtenir une catégorie spécifique
     */
    public function show(GroupCategory $category): JsonResponse
    {
        return response()->json([
            'category' => $category,
        ]);
    }
}
