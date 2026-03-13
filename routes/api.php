<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\ConfessionController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupCategoryController;
use App\Http\Controllers\Api\V1\GiftController;
use App\Http\Controllers\Api\V1\PremiumController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\StoryController;
use App\Http\Controllers\Api\V1\CinetPayController;
use App\Http\Controllers\Api\V1\FreemopayController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\PremiumPassController;
use App\Http\Controllers\Api\V1\AnonymousMessageRevealController;
use App\Http\Controllers\Api\V1\SponsorshipPackageController;
use App\Http\Controllers\Api\V1\SponsorshipController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Admin\MaintenanceController;
use App\Http\Controllers\Api\LegalPageController;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ==================== BROADCASTING AUTH ====================
    // Rate limit plus généreux pour l'authentification WebSocket
    // 120 requêtes par minute au lieu du défaut (60)
    Broadcast::routes(['middleware' => ['auth:sanctum', 'throttle:120,1']]);

    // ==================== AUTH ROUTES (Public) ====================
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/verify-identity', [AuthController::class, 'verifyIdentity']);
        Route::post('/reset-password-by-phone', [AuthController::class, 'resetPasswordByPhone']);
        Route::post('/register-and-send', [AuthController::class, 'registerAndSend']);
    });

    // ==================== PUBLIC USER PROFILE ====================
    Route::get('/users/by-username/{username}', [UserController::class, 'show']);
    Route::get('/users/by-id/{id}', [UserController::class, 'showById']);
    Route::get('/users/{username}', [UserController::class, 'show'])->where('username', '^(?!dashboard|profile|settings|blocked|stats).*$');

    // ==================== PUBLIC CONFESSIONS FEED ====================
    Route::get('/confessions', [ConfessionController::class, 'index']);
    Route::get('/confessions/{confession}', [ConfessionController::class, 'show'])->where('confession', '[0-9]+');
    Route::get('/confessions/{confession}/comments', [ConfessionController::class, 'getComments'])->where('confession', '[0-9]+');

    // ==================== PUBLIC GIFTS CATALOG ====================
    Route::get('/gifts', [GiftController::class, 'index']);
    Route::get('/gifts/{gift}', [GiftController::class, 'show'])->where('gift', '[0-9]+');
    Route::get('/gift-categories', [GiftController::class, 'getCategories']);
    Route::get('/gift-categories/{categoryId}/gifts', [GiftController::class, 'getGiftsByCategory']);

    // ==================== PUBLIC SPONSORSHIP PACKAGES ====================
    Route::get('/sponsorship-packages', [SponsorshipPackageController::class, 'index']);

    // ==================== PUBLIC GROUP CATEGORIES ====================
    Route::get('/group-categories', [GroupCategoryController::class, 'index']);
    Route::get('/group-categories/{category}', [GroupCategoryController::class, 'show']);

    // ==================== PREMIUM PRICING ====================
    Route::get('/premium/pricing', [PremiumController::class, 'pricing']);

    // ==================== PREMIUM PASS INFO ====================
    Route::get('/premium-pass/info', [PremiumPassController::class, 'info']);

    // ==================== LEGAL PAGES ====================
    Route::get('/legal-pages', [LegalPageController::class, 'index']);
    Route::get('/legal-pages/{slug}', [LegalPageController::class, 'show']);

    // ==================== PUBLIC SETTINGS ====================
    Route::get('/settings/public', [SettingController::class, 'getPublicSettings']);
    Route::get('/settings/reveal-price', [SettingController::class, 'getRevealPrice']);

    // ==================== REVEAL IDENTITY PRICE (Public) ====================
    Route::get('/reveal-identity/price', [AnonymousMessageRevealController::class, 'getRevealPrice']);

    // ==================== MAINTENANCE MODE STATUS (Public) ====================
    Route::get('/maintenance/status', [MaintenanceController::class, 'getStatus']);

    // ==================== PAYMENT WEBHOOKS (Public) ====================
    Route::prefix('payments')->group(function () {
        Route::post('/webhook/ligosapp', [PaymentController::class, 'webhookLigosApp']);
        Route::post('/webhook/intouch', [PaymentController::class, 'webhookIntouch']);
    });

    // ==================== CINETPAY WEBHOOKS (Public) ====================
    Route::prefix('cinetpay')->group(function () {
        // CinetPay teste l'URL avec GET avant d'envoyer POST
        Route::match(['get', 'post'], '/notify', [CinetPayController::class, 'handleNotification']);
        Route::match(['get', 'post'], '/return', [CinetPayController::class, 'handleReturn']);
    });

    // ==================== FREEMOPAY WEBHOOKS (Public) ====================
    Route::post('/webhooks/freemopay', [FreemopayController::class, 'handleCallback']);

    // ==================== AUTHENTICATED ROUTES ====================
    Route::middleware('auth:sanctum')->group(function () {

        // ==================== AUTH ====================
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
            Route::post('/resend-email-verification', [AuthController::class, 'resendEmailVerification']);
            Route::post('/verify-phone', [AuthController::class, 'verifyPhone']);
            Route::put('/update-pin-direct', [AuthController::class, 'updatePinDirect']);
        });

        // ==================== USERS ====================
        Route::prefix('users')->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::put('/profile', [UserController::class, 'updateProfile']);
            Route::put('/settings', [UserController::class, 'updateSettings']);
            Route::put('/password', [UserController::class, 'changePassword']);
            Route::post('/avatar', [UserController::class, 'uploadAvatar']);
            Route::delete('/avatar', [UserController::class, 'deleteAvatar']);
            Route::post('/cover-photo', [UserController::class, 'uploadCoverPhoto']);
            Route::delete('/cover-photo', [UserController::class, 'deleteCoverPhoto']);
            Route::get('/dashboard', [UserController::class, 'dashboard']);
            Route::get('/stats', [UserController::class, 'getStats']);
            Route::post('/fcm-token', [UserController::class, 'saveFcmToken']);
            Route::delete('/account', [UserController::class, 'deleteAccount']);
            Route::get('/share-link', [UserController::class, 'shareLink']);

            // Blocages
            Route::get('/blocked', [UserController::class, 'blockedUsers']);
            Route::post('/{username}/block', [UserController::class, 'block']);
            Route::delete('/{username}/block', [UserController::class, 'unblock']);
        });

        // ==================== MESSAGES ANONYMES ====================
        Route::prefix('messages')->group(function () {
            Route::get('/', [MessageController::class, 'index']);
            Route::get('/sent', [MessageController::class, 'sent']);
            Route::get('/stats', [MessageController::class, 'stats']);
            Route::post('/read-all', [MessageController::class, 'markAllAsRead']);
            Route::post('/send/reply', [MessageController::class, 'sendReply']); // Route spécifique pour les réponses
            Route::get('/{message}', [MessageController::class, 'show']);
            Route::post('/send/{username}', [MessageController::class, 'send']);
            Route::post('/{message}/reveal', [MessageController::class, 'reveal']);
            Route::post('/{message}/start-conversation', [MessageController::class, 'startConversation']);
            Route::post('/{message}/report', [MessageController::class, 'report']);
            Route::delete('/{message}', [MessageController::class, 'destroy']);
        });

        // ==================== RÉVÉLATION IDENTITÉ ANONYME (PAIEMENT) ====================
        Route::prefix('reveal-identity')->group(function () {
            Route::post('/messages/{message}/initiate', [AnonymousMessageRevealController::class, 'initiatePayment']);
            Route::get('/messages/{message}/status', [AnonymousMessageRevealController::class, 'checkPaymentStatus']);

            Route::post('/conversations/{conversation}/initiate', [ChatController::class, 'initiateRevealPayment']);
            Route::get('/conversations/{conversation}/status', [ChatController::class, 'checkRevealPaymentStatus']);
        });

        // ==================== CONFESSIONS ====================
        Route::prefix('confessions')->group(function () {
            Route::get('/received', [ConfessionController::class, 'received']);
            Route::get('/sent', [ConfessionController::class, 'sent']);
            Route::get('/stats', [ConfessionController::class, 'stats']);
            Route::get('/favorites', [ConfessionController::class, 'favorites']);
            Route::post('/', [ConfessionController::class, 'store']);
            Route::put('/{confession}', [ConfessionController::class, 'update']);
            Route::post('/{confession}/like', [ConfessionController::class, 'like']);
            Route::delete('/{confession}/like', [ConfessionController::class, 'unlike']);
            Route::post('/{confession}/favorite', [ConfessionController::class, 'toggleFavorite']);
            Route::post('/{confession}/reveal', [ConfessionController::class, 'reveal']);
            Route::post('/{confession}/reveal-identity', [ConfessionController::class, 'revealIdentity']);
            Route::post('/{confession}/report', [ConfessionController::class, 'report']);
            Route::post('/{confession}/comments', [ConfessionController::class, 'addComment']);
            Route::post('/{confession}/comments/{comment}/like', [ConfessionController::class, 'likeComment']);
            Route::delete('/{confession}/comments/{comment}/like', [ConfessionController::class, 'unlikeComment']);
            Route::delete('/{confession}/comments/{comment}', [ConfessionController::class, 'deleteComment']);
            Route::delete('/{confession}', [ConfessionController::class, 'destroy']);
        });

        // ==================== CHAT ====================
        Route::prefix('chat')->group(function () {
            Route::get('/conversations', [ChatController::class, 'conversations']);
            Route::post('/conversations', [ChatController::class, 'start']);
            Route::get('/conversations/{conversation}', [ChatController::class, 'show']);
            Route::get('/conversations/{conversation}/messages', [ChatController::class, 'messages']);
            Route::post('/conversations/{conversation}/messages', [ChatController::class, 'sendMessage']);
            Route::patch('/conversations/{conversation}/messages/{message}', [ChatController::class, 'updateMessage']);
            Route::post('/conversations/{conversation}/typing', [ChatController::class, 'updateTypingStatus']);
            Route::post('/conversations/{conversation}/read', [ChatController::class, 'markAsRead']);
            Route::post('/conversations/{conversation}/reveal', [ChatController::class, 'revealIdentity']);
            Route::post('/conversations/{conversation}/gift', [GiftController::class, 'sendInConversation']);
            Route::delete('/conversations/{conversation}', [ChatController::class, 'destroy']);
            Route::get('/unread-count', [ChatController::class, 'unreadCount']);
            Route::get('/stats', [ChatController::class, 'stats']);
            Route::get('/user-status/{username}', [ChatController::class, 'userStatus']);
            Route::post('/presence', [ChatController::class, 'updatePresence']);
        });

        // ==================== GROUPS ====================
        Route::prefix('groups')->group(function () {
            Route::get('/', [GroupController::class, 'index']);
            Route::get('/discover', [GroupController::class, 'discover']); // Découvrir les groupes publics
            Route::post('/', [GroupController::class, 'store']);
            Route::post('/join', [GroupController::class, 'join']);
            Route::get('/stats', [GroupController::class, 'stats']);
            Route::get('/unread-count', [GroupController::class, 'unreadCount']);
            Route::get('/{group}', [GroupController::class, 'show']);
            Route::put('/{group}', [GroupController::class, 'update']);
            Route::delete('/{group}', [GroupController::class, 'destroy']);
            Route::post('/{group}/leave', [GroupController::class, 'leave']);
            Route::get('/{group}/messages', [GroupController::class, 'messages']);
            Route::post('/{group}/messages', [GroupController::class, 'sendMessage']);
            Route::delete('/{group}/messages/{message}', [GroupController::class, 'deleteMessage']);
            Route::post('/{group}/read', [GroupController::class, 'markAsRead']);
            Route::get('/{group}/members', [GroupController::class, 'members']);
            Route::delete('/{group}/members/{member}', [GroupController::class, 'removeMember']);
            Route::put('/{group}/members/{member}/role', [GroupController::class, 'updateMemberRole']);
            Route::post('/{group}/regenerate-invite', [GroupController::class, 'regenerateInviteCode']);
        });

        // ==================== CADEAUX ====================
        Route::prefix('gifts')->group(function () {
            Route::get('/received', [GiftController::class, 'received']);
            Route::get('/sent', [GiftController::class, 'sent']);
            Route::get('/stats', [GiftController::class, 'stats']);
            Route::post('/send', [GiftController::class, 'send']);
        });

        // ==================== PREMIUM ====================
        Route::prefix('premium')->group(function () {
            Route::get('/subscriptions', [PremiumController::class, 'index']);
            Route::get('/subscriptions/active', [PremiumController::class, 'active']);
            Route::post('/subscribe/message/{message}', [PremiumController::class, 'subscribeToMessage']);
            Route::post('/subscribe/conversation/{conversation}', [PremiumController::class, 'subscribeToConversation']);
            Route::post('/subscribe/story/{story}', [PremiumController::class, 'subscribeToStory']);
            Route::post('/cancel/{subscription}', [PremiumController::class, 'cancel']);
            Route::get('/check', [PremiumController::class, 'check']);
        });

        // ==================== PREMIUM PASS ====================
        Route::prefix('premium-pass')->group(function () {
            Route::get('/status', [PremiumPassController::class, 'status']);
            Route::post('/purchase', [PremiumPassController::class, 'purchase']);
            Route::post('/renew', [PremiumPassController::class, 'renew']);
            Route::post('/auto-renew/enable', [PremiumPassController::class, 'enableAutoRenew']);
            Route::post('/auto-renew/disable', [PremiumPassController::class, 'disableAutoRenew']);
            Route::get('/history', [PremiumPassController::class, 'history']);
            Route::get('/can-view-identity/{userId}', [PremiumPassController::class, 'canViewIdentity']);
        });

        // ==================== WALLET ====================
        Route::prefix('wallet')->group(function () {
            Route::get('/', [WalletController::class, 'index']);
            Route::get('/transactions', [WalletController::class, 'transactions']);
            Route::get('/stats', [WalletController::class, 'stats']);
            Route::get('/withdrawal-methods', [WalletController::class, 'withdrawalMethods']);

            // Dépôts
            Route::post('/deposit/initiate', [WalletController::class, 'initiateDeposit']);
            Route::post('/deposit/check-status', [WalletController::class, 'checkDepositStatus']);

            // Retraits
            Route::post('/withdraw', [WalletController::class, 'withdraw']);
            Route::get('/withdrawals', [WalletController::class, 'withdrawals']);
            Route::get('/withdrawals/{withdrawal}', [WalletController::class, 'showWithdrawal']);
            Route::delete('/withdrawals/{withdrawal}', [WalletController::class, 'cancelWithdrawal']);
        });

        // ==================== SPONSORSHIP ====================
        Route::prefix('sponsorships')->group(function () {
            Route::post('/purchase', [SponsorshipController::class, 'purchase']);
            Route::get('/feed', [SponsorshipController::class, 'feed']);
            Route::get('/mine', [SponsorshipController::class, 'mine']);
            Route::get('/dashboard', [SponsorshipController::class, 'dashboard']);
            Route::post('/{sponsorship}/impression', [SponsorshipController::class, 'impression']);
        });

        // ==================== PAYMENT PROVIDERS CONFIG ====================
        Route::get('/payment-providers/config', [\App\Http\Controllers\Api\V1\PaymentProviderController::class, 'getConfig']);

        // ==================== CINETPAY (Authenticated) ====================
        Route::prefix('cinetpay')->group(function () {
            Route::post('/deposit/initiate', [CinetPayController::class, 'initiateDepositPayment']);
            Route::post('/check-status', [CinetPayController::class, 'checkTransactionStatus']);
            Route::post('/withdrawal/initiate', [CinetPayController::class, 'initiateWithdrawal']);
            Route::post('/withdrawal/status', [CinetPayController::class, 'checkWithdrawalStatus']);
        });

        // ==================== FREEMOPAY (Authenticated) ====================
        Route::prefix('freemopay')->group(function () {
            Route::post('/deposit/initiate', [FreemopayController::class, 'initiateDepositPayment']);
            Route::post('/check-status', [FreemopayController::class, 'checkTransactionStatus']);
            Route::post('/withdrawal/initiate', [FreemopayController::class, 'initiateWithdrawal']);
            Route::post('/withdrawal/status', [FreemopayController::class, 'checkWithdrawalStatus']);
        });

        // ==================== NOTIFICATIONS ====================
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{notification}', [NotificationController::class, 'destroy']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        });

        // ==================== STORIES ====================
        Route::prefix('stories')->group(function () {
            Route::get('/', [StoryController::class, 'index']);
            Route::get('/my-stories', [StoryController::class, 'myStories']);
            Route::get('/stats', [StoryController::class, 'stats']);
            Route::post('/', [StoryController::class, 'store']);
            Route::get('/user/{username}', [StoryController::class, 'userStories']);
            Route::get('/user-by-id/{userId}', [StoryController::class, 'userStoriesById']);
            Route::get('/{story}', [StoryController::class, 'show']);
            Route::post('/{story}/view', [StoryController::class, 'markAsViewed']);
            Route::get('/{story}/viewers', [StoryController::class, 'viewers']);
            Route::delete('/{story}', [StoryController::class, 'destroy']);
        });

        // ==================== PAYMENTS ====================
        Route::prefix('payments')->group(function () {
            Route::get('/status/{reference}', [PaymentController::class, 'checkStatus']);
        });

        // ==================== ADMIN ROUTES ====================
        Route::middleware('admin')->prefix('admin')->group(function () {

            // Dashboard
            Route::get('/dashboard', [AdminDashboardController::class, 'index']);
            Route::get('/analytics', [AdminDashboardController::class, 'analytics']);
            Route::get('/revenue', [AdminDashboardController::class, 'revenue']);
            Route::get('/recent-activity', [AdminDashboardController::class, 'recentActivity']);

            // Users Management
            Route::prefix('users')->group(function () {
                Route::get('/', [AdminUserController::class, 'index']);
                Route::get('/stats', [AdminUserController::class, 'stats']);
                Route::get('/{user}', [AdminUserController::class, 'show']);
                Route::put('/{user}', [AdminUserController::class, 'update']);
                Route::post('/{user}/ban', [AdminUserController::class, 'ban']);
                Route::post('/{user}/unban', [AdminUserController::class, 'unban']);
                Route::delete('/{user}', [AdminUserController::class, 'destroy']);
                Route::get('/{user}/logs', [AdminUserController::class, 'adminLogs']);
            });

            // Moderation
            Route::prefix('moderation')->group(function () {
                // Confessions
                Route::get('/confessions', [ModerationController::class, 'confessions']);
                Route::post('/confessions/{confession}/approve', [ModerationController::class, 'approveConfession']);
                Route::post('/confessions/{confession}/reject', [ModerationController::class, 'rejectConfession']);
                Route::delete('/confessions/{confession}', [ModerationController::class, 'deleteConfession']);

                // Reports
                Route::get('/reports', [ModerationController::class, 'reports']);
                Route::get('/reports/{report}', [ModerationController::class, 'showReport']);
                Route::post('/reports/{report}/resolve', [ModerationController::class, 'resolveReport']);
                Route::post('/reports/{report}/dismiss', [ModerationController::class, 'dismissReport']);
                Route::post('/reports/{report}/resolve-and-ban', [ModerationController::class, 'resolveAndBan']);

                // Stats
                Route::get('/stats', [ModerationController::class, 'stats']);
            });

            // Withdrawals
            Route::prefix('withdrawals')->group(function () {
                Route::get('/', [AdminWithdrawalController::class, 'index']);
                Route::get('/stats', [AdminWithdrawalController::class, 'stats']);
                Route::get('/export', [AdminWithdrawalController::class, 'export']);
                Route::get('/{withdrawal}', [AdminWithdrawalController::class, 'show']);
                Route::post('/{withdrawal}/process', [AdminWithdrawalController::class, 'process']);
                Route::post('/{withdrawal}/reject', [AdminWithdrawalController::class, 'reject']);
            });

            // Maintenance Mode
            Route::prefix('maintenance')->group(function () {
                Route::put('/update', [MaintenanceController::class, 'update']);
            });
        });
    });
});
