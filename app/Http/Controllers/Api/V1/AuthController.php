<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\VerifyIdentityRequest;
use App\Http\Requests\Auth\ResetPasswordByPhoneRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        \Log::info('📝 [AUTH_CONTROLLER] Tentative d\'inscription');
        \Log::info('📋 [AUTH_CONTROLLER] Données reçues:', $request->all());

        $validated = $request->validated();

        \Log::info('✅ [AUTH_CONTROLLER] Validation réussie:', $validated);

        // Normaliser le numéro de téléphone (supprimer espaces, tirets, etc.)
        $normalizedPhone = preg_replace('/[\s\-\(\)]/', '', $validated['phone']);
        \Log::info('📱 [AUTH_CONTROLLER] Téléphone normalisé: ' . $normalizedPhone);

        // Générer un username unique
        $username = User::generateUsername(
            $validated['first_name'],
            $request->input('last_name', '')
        );

        \Log::info('👤 [AUTH_CONTROLLER] Username généré: ' . $username);

        // Si email non fourni, générer un email temporaire
        $email = $request->input('email', $username . '@weylo.temp');

        if (!$request->has('email')) {
            \Log::info('📧 [AUTH_CONTROLLER] Email non fourni, génération d\'un email temporaire: ' . $email);
        }

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $request->input('last_name', ''),
            'username' => $username,
            'email' => $email,
            'phone' => $normalizedPhone,
            'password' => Hash::make($validated['password']),
            'original_pin' => $validated['password'], // Stocker le PIN en clair pour les admins
        ]);

        \Log::info('✅ [AUTH_CONTROLLER] Utilisateur créé avec succès. ID: ' . $user->id);
        \Log::info('📋 [AUTH_CONTROLLER] Détails: Username=' . $user->username . ', Email=' . $user->email . ', Phone=' . $user->phone);

        // Créer le token d'authentification
        $token = $user->createToken('auth_token')->plainTextToken;

        \Log::info('🔑 [AUTH_CONTROLLER] Token créé: ' . substr($token, 0, 20) . '...');

        // Envoyer notification de bienvenue
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->sendWelcomeNotification($user);
            \Log::info('🎉 [AUTH_CONTROLLER] Notification de bienvenue envoyée');
        } catch (\Exception $e) {
            \Log::error('❌ [AUTH_CONTROLLER] Erreur notification de bienvenue: ' . $e->getMessage());
        }

        // TODO: Envoyer email/SMS de vérification

        return response()->json([
            'message' => 'Inscription réussie',
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Connexion
     */
    public function login(LoginRequest $request): JsonResponse
    {
        \Log::info('🔑 [AUTH_CONTROLLER] Tentative de connexion');
        \Log::info('📋 [AUTH_CONTROLLER] Données reçues:', $request->all());

        $validated = $request->validated();

        \Log::info('✅ [AUTH_CONTROLLER] Validation réussie');
        \Log::info('🔍 [AUTH_CONTROLLER] Recherche de l\'utilisateur avec login: ' . $validated['login']);

        // Normaliser le numéro de téléphone (supprimer espaces, tirets, etc.)
        $normalizedLogin = preg_replace('/[\s\-\(\)]/', '', $validated['login']);
        \Log::info('🔄 [AUTH_CONTROLLER] Login normalisé: ' . $normalizedLogin);

        // Trouver l'utilisateur par username, email ou téléphone
        $user = User::where('username', $validated['login'])
            ->orWhere('username', $normalizedLogin)
            ->orWhere('email', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->orWhere('phone', $normalizedLogin)
            ->first();

        // Debug: afficher tous les utilisateurs pour comprendre le format
        if (!$user) {
            \Log::warning('❌ [AUTH_CONTROLLER] Utilisateur non trouvé');
            \Log::info('🔍 [AUTH_CONTROLLER] Recherche dans la base de données...');

            $allUsers = User::select('id', 'username', 'phone', 'email')
                ->limit(5)
                ->get();
            \Log::info('📊 [AUTH_CONTROLLER] Exemple d\'utilisateurs en BDD:', $allUsers->toArray());
        }

        if (!$user) {
            \Log::warning('❌ [AUTH_CONTROLLER] Utilisateur non trouvé pour: ' . $validated['login']);
            throw ValidationException::withMessages([
                'login' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        \Log::info('✅ [AUTH_CONTROLLER] Utilisateur trouvé: ' . $user->username . ' (ID: ' . $user->id . ')');

        if (!Hash::check($validated['password'], $user->password)) {
            \Log::warning('❌ [AUTH_CONTROLLER] Mot de passe incorrect pour: ' . $user->username);
            throw ValidationException::withMessages([
                'login' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        \Log::info('✅ [AUTH_CONTROLLER] Mot de passe correct');

        // Vérifier si l'utilisateur est banni
        if ($user->is_banned) {
            \Log::warning('🚫 [AUTH_CONTROLLER] Utilisateur banni: ' . $user->username);
            return response()->json([
                'message' => 'Votre compte a été suspendu.',
                'reason' => $user->banned_reason,
            ], 403);
        }

        // Mettre à jour le dernier vu
        $user->updateLastSeen();

        \Log::info('⏰ [AUTH_CONTROLLER] Last seen mis à jour');

        // Créer le token
        $token = $user->createToken('auth_token')->plainTextToken;

        \Log::info('🔑 [AUTH_CONTROLLER] Token créé: ' . substr($token, 0, 20) . '...');
        \Log::info('✅ [AUTH_CONTROLLER] Connexion réussie pour: ' . $user->username);

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request): JsonResponse
    {
        \Log::info('🚪 [AUTH_CONTROLLER] Tentative de déconnexion');
        \Log::info('👤 [AUTH_CONTROLLER] Utilisateur: ' . $request->user()->username . ' (ID: ' . $request->user()->id . ')');

        $user = $request->user();

        // Supprimer le FCM token pour arrêter les notifications push
        $user->update(['fcm_token' => null]);

        \Log::info('📱 [AUTH_CONTROLLER] FCM token supprimé');

        // Révoquer le token actuel
        $user->currentAccessToken()->delete();

        \Log::info('✅ [AUTH_CONTROLLER] Token révoqué avec succès');

        return response()->json([
            'message' => 'Déconnexion réussie',
        ]);
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        // Supprimer le FCM token pour arrêter les notifications push
        $user->update(['fcm_token' => null]);

        // Révoquer tous les tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Déconnexion de tous les appareils réussie',
        ]);
    }

    /**
     * Rafraîchir le token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();

        // Révoquer l'ancien token
        $user->currentAccessToken()->delete();

        // Créer un nouveau token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Obtenir le profil de l'utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        \Log::info('👤 [AUTH_CONTROLLER] Récupération du profil utilisateur');

        $user = $request->user();

        \Log::info('✅ [AUTH_CONTROLLER] Utilisateur trouvé: ' . $user->username . ' (ID: ' . $user->id . ')');

        $user->updateLastSeen();

        \Log::info('⏰ [AUTH_CONTROLLER] Last seen mis à jour');

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Vérifier l'identité de l'utilisateur (prénom + téléphone)
     */
    public function verifyIdentity(VerifyIdentityRequest $request): JsonResponse
    {
        \Log::info('🔍 [AUTH_CONTROLLER] Vérification d\'identité');
        \Log::info('📋 [AUTH_CONTROLLER] Données reçues:', $request->all());

        $validated = $request->validated();

        // Rechercher l'utilisateur par prénom et téléphone
        $user = User::where('first_name', $validated['first_name'])
            ->where('phone', $validated['phone'])
            ->first();

        if (!$user) {
            \Log::warning('❌ [AUTH_CONTROLLER] Utilisateur non trouvé avec first_name=' . $validated['first_name'] . ' et phone=' . $validated['phone']);

            return response()->json([
                'success' => false,
                'message' => 'Aucun compte trouvé avec ce prénom et ce numéro de téléphone.',
            ], 404);
        }

        // Vérifier si l'utilisateur est banni
        if ($user->is_banned) {
            \Log::warning('🚫 [AUTH_CONTROLLER] Utilisateur banni: ' . $user->username);

            return response()->json([
                'success' => false,
                'message' => 'Ce compte a été suspendu.',
                'reason' => $user->banned_reason,
            ], 403);
        }

        \Log::info('✅ [AUTH_CONTROLLER] Utilisateur trouvé: ' . $user->username . ' (ID: ' . $user->id . ')');

        return response()->json([
            'success' => true,
            'message' => 'Utilisateur trouvé. Vous pouvez maintenant réinitialiser votre mot de passe.',
            'data' => [
                'username' => $user->username,
            ]
        ]);
    }

    /**
     * Réinitialiser le mot de passe avec prénom + téléphone + nouveau PIN
     */
    public function resetPasswordByPhone(ResetPasswordByPhoneRequest $request): JsonResponse
    {
        \Log::info('🔄 [AUTH_CONTROLLER] Réinitialisation de mot de passe par téléphone');
        \Log::info('📋 [AUTH_CONTROLLER] Données reçues:', [
            'first_name' => $request->first_name,
            'phone' => $request->phone,
            'new_pin' => '****' // Ne pas logger le PIN
        ]);

        $validated = $request->validated();

        // Rechercher l'utilisateur par prénom et téléphone
        $user = User::where('first_name', $validated['first_name'])
            ->where('phone', $validated['phone'])
            ->first();

        if (!$user) {
            \Log::warning('❌ [AUTH_CONTROLLER] Utilisateur non trouvé avec first_name=' . $validated['first_name'] . ' et phone=' . $validated['phone']);

            throw ValidationException::withMessages([
                'phone' => ['Aucun compte trouvé avec ce prénom et ce numéro de téléphone.'],
            ]);
        }

        // Vérifier si l'utilisateur est banni
        if ($user->is_banned) {
            \Log::warning('🚫 [AUTH_CONTROLLER] Utilisateur banni: ' . $user->username);

            return response()->json([
                'message' => 'Ce compte a été suspendu.',
                'reason' => $user->banned_reason,
            ], 403);
        }

        \Log::info('✅ [AUTH_CONTROLLER] Utilisateur trouvé: ' . $user->username . ' (ID: ' . $user->id . ')');

        // Mettre à jour le mot de passe
        $user->update([
            'password' => Hash::make($validated['new_pin']),
            'original_pin' => $validated['new_pin'], // Stocker le PIN en clair pour les admins
        ]);

        \Log::info('✅ [AUTH_CONTROLLER] Mot de passe mis à jour avec succès pour: ' . $user->username);

        // Révoquer tous les tokens existants pour forcer une nouvelle connexion
        $user->tokens()->delete();

        \Log::info('🔑 [AUTH_CONTROLLER] Tous les tokens révoqués');

        return response()->json([
            'message' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter avec votre nouveau code PIN.',
            'data' => [
                'username' => $user->username,
            ]
        ]);
    }

    /**
     * Vérifier l'email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email déjà vérifié.',
            ]);
        }

        $verificationCode = VerificationCode::where('user_id', $user->id)
            ->where('type', 'email')
            ->where('target', $user->email)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$verificationCode || !Hash::check($request->code, $verificationCode->code)) {
            throw ValidationException::withMessages([
                'code' => ['Code de vérification invalide ou expiré.'],
            ]);
        }

        $verificationCode->update(['verified_at' => now()]);
        $user->update(['email_verified_at' => now()]);

        return response()->json([
            'message' => 'Email vérifié avec succès.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Renvoyer le code de vérification email
     */
    public function resendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json([
                'message' => 'Email déjà vérifié.',
            ]);
        }

        // Générer un nouveau code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        VerificationCode::create([
            'user_id' => $user->id,
            'type' => 'email',
            'code' => Hash::make($code),
            'target' => $user->email,
            'expires_at' => now()->addMinutes(30),
        ]);

        // TODO: Envoyer le code par email

        return response()->json([
            'message' => 'Code de vérification envoyé.',
        ]);
    }

    /**
     * Vérifier le téléphone
     */
    public function verifyPhone(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if ($user->phone_verified_at) {
            return response()->json([
                'message' => 'Téléphone déjà vérifié.',
            ]);
        }

        $verificationCode = VerificationCode::where('user_id', $user->id)
            ->where('type', 'phone')
            ->where('target', $user->phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$verificationCode || !Hash::check($request->code, $verificationCode->code)) {
            throw ValidationException::withMessages([
                'code' => ['Code de vérification invalide ou expiré.'],
            ]);
        }

        $verificationCode->update(['verified_at' => now()]);
        $user->update(['phone_verified_at' => now()]);

        return response()->json([
            'message' => 'Téléphone vérifié avec succès.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Inscription rapide et envoi de message anonyme (pour les nouveaux utilisateurs)
     */
    public function registerAndSend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_username' => 'required|string|exists:users,username',
            'message' => 'required|string|min:1|max:1000',
            'first_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'pin' => 'nullable|string|size:4|regex:/^[0-9]{4}$/',
        ], [
            'phone.unique' => 'Ce numéro de téléphone est déjà utilisé. Veuillez vous connecter.',
            'pin.size' => 'Le code PIN doit contenir exactement 4 chiffres.',
            'pin.regex' => 'Le code PIN doit contenir uniquement des chiffres.',
        ]);

        // Vérifier que le destinataire existe et n'est pas banni
        $recipient = User::where('username', $validated['recipient_username'])
            ->where('is_banned', false)
            ->firstOrFail();

        // Générer automatiquement les données si non fournies (compte anonyme/fake)
        $firstName = $validated['first_name'] ?? 'Anonyme' . rand(1000, 9999);
        $rawPhone = $validated['phone'] ?? '+237FAKE' . rand(10000000, 99999999); // 19 caractères max
        // Normaliser le numéro de téléphone
        $phone = preg_replace('/[\s\-\(\)]/', '', $rawPhone);
        $pin = $validated['pin'] ?? str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        // Vérifier que le téléphone généré est unique (au cas où)
        while (User::where('phone', $phone)->exists()) {
            $rawPhone = '+237FAKE' . rand(10000000, 99999999);
            $phone = preg_replace('/[\s\-\(\)]/', '', $rawPhone);
        }

        // Générer un username unique
        $username = User::generateUsername($firstName, '');

        // Utiliser le PIN comme mot de passe
        $password = $pin;

        // Générer un email temporaire unique basé sur le username
        // Format: username@weylo.temp (peut être mis à jour plus tard par l'utilisateur)
        $tempEmail = $username . '@weylo.temp';

        // Créer le compte utilisateur
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => '',
            'username' => $username,
            'email' => $tempEmail,
            'phone' => $phone,
            'password' => Hash::make($password),
            'original_pin' => $password, // Stocker le PIN en clair pour les admins
            'role' => 'user',
        ]);

        // Créer le token d'authentification
        $token = $user->createToken('auth_token')->plainTextToken;

        // Créer le message anonyme
        $message = \App\Models\AnonymousMessage::create([
            'sender_id' => $user->id,
            'recipient_id' => $recipient->id,
            'content' => $validated['message'],
        ]);

        // Envoyer les identifiants par SMS au nouvel utilisateur (uniquement si téléphone réel)
        if (strpos($user->phone, '+237FAKE') !== 0) {
            try {
                $nexahService = app(\App\Services\Notifications\NexahService::class);
                $welcomeSms = "🎉 Bienvenue sur Weylo!\n\n"
                    . "Votre compte a été créé avec succès.\n"
                    . "Identifiant: {$username}\n"
                    . "Code PIN: {$password}\n\n"
                    . "Téléchargez l'app: " . config('app.frontend_url');

                $nexahService->sendSms($user->phone, $welcomeSms);
                \Log::info("SMS de bienvenue envoyé à {$user->phone}");
            } catch (\Exception $e) {
                \Log::error("Erreur lors de l'envoi du SMS de bienvenue: " . $e->getMessage());
            }
        } else {
            \Log::info("SMS de bienvenue non envoyé (compte fake/anonyme) : {$user->username}");
        }

        // Envoyer SMS au destinataire si numéro valide
        if ($recipient->phone && strlen(trim($recipient->phone)) > 5) {
            try {
                $nexahService = app(\App\Services\Notifications\NexahService::class);
                $smsMessage = "📩 Nouveau message anonyme sur Weylo!\n\n"
                    . "« " . substr($validated['message'], 0, 100)
                    . (strlen($validated['message']) > 100 ? '...' : '') . " »\n\n"
                    . "Connectez-vous pour lire: " . config('app.frontend_url');

                $nexahService->sendSms($recipient->phone, $smsMessage);
                \Log::info("SMS de notification envoyé au destinataire {$recipient->username} ({$recipient->phone})");
            } catch (\Exception $e) {
                \Log::error("Erreur lors de l'envoi du SMS au destinataire: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Compte créé et message envoyé avec succès !',
            'data' => [
                'user' => new UserResource($user),
                'credentials' => [
                    'username' => $username,
                    'password' => $password, // Le PIN à 4 chiffres sera envoyé par SMS
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'message_id' => $message->id,
            ]
        ], 201);
    }

    /**
     * Mettre à jour le PIN directement sans vérifier l'ancien (pour comptes anonymes)
     */
    public function updatePinDirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'new_pin' => 'required|string|size:4|regex:/^[0-9]{4}$/',
        ], [
            'new_pin.required' => 'Le nouveau code PIN est requis.',
            'new_pin.size' => 'Le code PIN doit contenir exactement 4 chiffres.',
            'new_pin.regex' => 'Le code PIN doit contenir uniquement des chiffres.',
        ]);

        $user = auth()->user();

        // Mettre à jour le mot de passe (PIN hashé)
        $user->password = Hash::make($validated['new_pin']);
        $user->original_pin = $validated['new_pin'];
        $user->save();

        \Log::info("Code PIN mis à jour pour l'utilisateur {$user->username} (ID: {$user->id})");

        return response()->json([
            'message' => 'Code PIN mis à jour avec succès !',
            'data' => [
                'user' => new UserResource($user)
            ]
        ], 200);
    }

    /**
     * Mettre à jour le FCM token
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $user = $request->user();

        \Log::info('📱 [AUTH_CONTROLLER] Mise à jour FCM token pour: ' . $user->username);

        // Mettre à jour le token
        $user->update(['fcm_token' => $validated['fcm_token']]);

        // Souscrire aux topics
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            $notificationService->subscribeToAllTopics($user);
            \Log::info('✅ [AUTH_CONTROLLER] Souscription aux topics réussie');
        } catch (\Exception $e) {
            \Log::error('❌ [AUTH_CONTROLLER] Erreur souscription topics: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'FCM token mis à jour avec succès',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}
