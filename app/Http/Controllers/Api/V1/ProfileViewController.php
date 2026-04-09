<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProfileView;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileViewController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Récupérer la liste des utilisateurs qui ont vu mon profil
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Récupérer toutes les vues de profil de l'utilisateur connecté
        $profileViews = ProfileView::where('viewed_id', $user->id)
            ->with('viewer:id,first_name,last_name,username,avatar,is_verified')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Formater les données selon si l'utilisateur est vérifié ou non
        $formattedViews = $profileViews->map(function ($view) use ($user) {
            $viewer = $view->viewer;

            // Si l'utilisateur n'est pas vérifié, masquer les informations
            if (!$user->is_verified) {
                return [
                    'id' => $view->id,
                    'viewer' => [
                        'id' => null,
                        'first_name' => 'Anonyme',
                        'last_name' => '',
                        'username' => null,
                        'avatar' => null,
                        'is_verified' => false,
                    ],
                    'viewed_at' => $view->created_at,
                    'formatted_time' => $this->formatRelativeTime($view->created_at),
                ];
            }

            // Si l'utilisateur est vérifié, afficher les informations complètes
            return [
                'id' => $view->id,
                'viewer' => [
                    'id' => $viewer->id,
                    'first_name' => $viewer->first_name,
                    'last_name' => $viewer->last_name,
                    'username' => $viewer->username,
                    'avatar' => $viewer->avatar,
                    'is_verified' => $viewer->is_verified,
                ],
                'viewed_at' => $view->created_at,
                'formatted_time' => $this->formatRelativeTime($view->created_at),
            ];
        });

        return response()->json([
            'views' => $formattedViews,
            'meta' => [
                'current_page' => $profileViews->currentPage(),
                'last_page' => $profileViews->lastPage(),
                'per_page' => $profileViews->perPage(),
                'total' => $profileViews->total(),
            ],
        ]);
    }

    /**
     * Enregistrer une vue de profil
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'viewed_user_id' => 'required|integer|exists:users,id',
        ]);

        $viewer = $request->user();
        $viewedUserId = $request->input('viewed_user_id');

        // Ne pas enregistrer si l'utilisateur regarde son propre profil
        if ($viewer->id === $viewedUserId) {
            return response()->json([
                'message' => 'Vous ne pouvez pas voir votre propre profil',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Vérifier si une vue existe déjà dans les 5 dernières minutes
            $recentView = ProfileView::where('viewer_id', $viewer->id)
                ->where('viewed_id', $viewedUserId)
                ->where('created_at', '>=', now()->subMinutes(5))
                ->first();

            if (!$recentView) {
                // Créer la vue de profil
                ProfileView::create([
                    'viewer_id' => $viewer->id,
                    'viewed_id' => $viewedUserId,
                ]);

                // Récupérer l'utilisateur dont le profil a été vu
                $viewedUser = User::findOrFail($viewedUserId);

                // Envoyer la notification (sauf si l'utilisateur visite son propre profil)
                if ($viewer->id !== $viewedUserId) {
                    $this->notificationService->sendProfileViewNotification($viewedUser);
                }

                Log::info('👁️ Profile view recorded', [
                    'viewer_id' => $viewer->id,
                    'viewed_id' => $viewedUserId,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Vue de profil enregistrée avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Failed to record profile view', [
                'viewer_id' => $viewer->id,
                'viewed_id' => $viewedUserId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur lors de l\'enregistrement de la vue de profil',
            ], 500);
        }
    }

    /**
     * Formater le temps relatif
     */
    private function formatRelativeTime($datetime): string
    {
        $now = now();
        $diff = $now->diffInSeconds($datetime);

        if ($diff < 60) {
            return 'À l\'instant';
        }

        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "Il y a {$minutes} min";
        }

        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "Il y a {$hours}h";
        }

        if ($diff < 604800) { // 7 jours
            $days = floor($diff / 86400);
            return "Il y a {$days}j";
        }

        // Sinon, afficher la date
        return $datetime->format('d M Y');
    }
}
