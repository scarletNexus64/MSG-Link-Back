<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminWebController;
use App\Http\Controllers\Admin\LegalPageController;
use App\Http\Controllers\Admin\MaintenanceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Weylo est principalement une API. Les routes web sont utilisées
| pour les redirections et les pages publiques simples.
|
*/

Route::get('/', function () {
    return redirect()->route('admin.login');
});

/*
|--------------------------------------------------------------------------
| Admin Dashboard Routes
|--------------------------------------------------------------------------
*/

// Admin Auth Routes
Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminWebController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AdminWebController::class, 'login'])->name('admin.login.submit');
    Route::post('/logout', [AdminWebController::class, 'logout'])->name('admin.logout');
});

// Protected Admin Routes
Route::prefix('admin')->middleware('auth')->group(function () {
    // Dashboard
    Route::get('/', [AdminWebController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/dashboard', [AdminWebController::class, 'dashboard']);
    Route::get('/analytics', [AdminWebController::class, 'analytics'])->name('admin.analytics');
    Route::get('/revenue', [AdminWebController::class, 'revenue'])->name('admin.revenue');
    Route::get('/settings', [AdminWebController::class, 'settings'])->name('admin.settings');
    Route::put('/settings', [AdminWebController::class, 'updateSettings'])->name('admin.settings.update');

    // Freemopay Settings
    Route::get('/settings/freemopay', [\App\Http\Controllers\Admin\FreemopaySettingsController::class, 'index'])->name('admin.settings.freemopay');
    Route::put('/settings/freemopay', [\App\Http\Controllers\Admin\FreemopaySettingsController::class, 'update'])->name('admin.settings.freemopay.update');
    Route::post('/settings/freemopay/test', [\App\Http\Controllers\Admin\FreemopaySettingsController::class, 'test'])->name('admin.settings.freemopay.test');

    // Wallets Management
    Route::prefix('wallets')->name('admin.wallets.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\WalletController::class, 'index'])->name('index');

        // Routes fixes AVANT les routes avec paramètres
        Route::get('transactions', [\App\Http\Controllers\Admin\WalletController::class, 'transactions'])->name('transactions');
        Route::get('withdrawals', [\App\Http\Controllers\Admin\WalletController::class, 'withdrawals'])->name('withdrawals');

        // Routes avec paramètres APRÈS
        Route::post('withdrawals/{withdrawal}/approve', [\App\Http\Controllers\Admin\WalletController::class, 'approveWithdrawal'])->name('withdrawals.approve');
        Route::post('withdrawals/{withdrawal}/reject', [\App\Http\Controllers\Admin\WalletController::class, 'rejectWithdrawal'])->name('withdrawals.reject');
        Route::post('withdrawals/{withdrawal}/complete', [\App\Http\Controllers\Admin\WalletController::class, 'completeWithdrawal'])->name('withdrawals.complete');
        Route::post('{user}/adjust', [\App\Http\Controllers\Admin\WalletController::class, 'adjustBalance'])->name('adjust');
        Route::get('{user}', [\App\Http\Controllers\Admin\WalletController::class, 'show'])->name('show');
    });

    // Profile
    Route::get('/profile', [AdminWebController::class, 'profile'])->name('admin.profile');
    Route::put('/profile', [AdminWebController::class, 'updateProfile'])->name('admin.profile.update');
    Route::put('/profile/password', [AdminWebController::class, 'updatePassword'])->name('admin.profile.password');
    Route::delete('/profile/avatar', [AdminWebController::class, 'deleteAvatar'])->name('admin.profile.avatar.delete');

    // Link Generator
    Route::get('/link-generator', [AdminWebController::class, 'linkGenerator'])->name('admin.link-generator');
    Route::get('/api/search-users', [AdminWebController::class, 'searchUsers'])->name('admin.api.search-users');

    // Cache management
    Route::post('/cache/clear', [AdminWebController::class, 'clearCache'])->name('admin.cache.clear');
    Route::post('/cache/config', [AdminWebController::class, 'clearConfigCache'])->name('admin.cache.config');

    // Legal Pages Management
    Route::resource('legal-pages', LegalPageController::class)->names('admin.legal-pages');

    // Service Configuration (WhatsApp, SMS, Payment)
    Route::prefix('service-config')->name('admin.service-config.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'index'])->name('index');
        Route::put('/whatsapp', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'updateWhatsApp'])->name('update-whatsapp');
        Route::put('/nexah', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'updateNexah'])->name('update-nexah');
        Route::put('/freemopay', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'updateFreeMoPay'])->name('update-freemopay');
        Route::put('/preferences', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'updateNotificationPreferences'])->name('update-preferences');

        // Test endpoints
        Route::post('/test/whatsapp', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'testWhatsApp'])->name('test-whatsapp');
        Route::post('/test/nexah', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'testNexah'])->name('test-nexah');
        Route::post('/test/freemopay', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'testFreeMoPay'])->name('test-freemopay');

        // Send test messages
        Route::post('/send-test/whatsapp', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'sendTestWhatsApp'])->name('send-test-whatsapp');
        Route::post('/send-test/nexah', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'sendTestNexah'])->name('send-test-nexah');

        // Clear cache
        Route::post('/clear-cache', [\App\Http\Controllers\Admin\ServiceConfigController::class, 'clearCache'])->name('clear-cache');
    });

    // Payment Configuration
    Route::prefix('payment-config')->name('admin.payment-config.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\PaymentConfigController::class, 'index'])->name('index');
        Route::put('/update', [\App\Http\Controllers\Admin\PaymentConfigController::class, 'update'])->name('update');
    });

    // Maintenance Mode
    Route::prefix('maintenance')->name('admin.maintenance.')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index');
        Route::post('/toggle', [MaintenanceController::class, 'toggle'])->name('toggle');
        Route::put('/update', [MaintenanceController::class, 'updateWeb'])->name('update');
    });

    // Team Management (admins & moderators)
    Route::prefix('team')->group(function () {
        Route::get('/', [AdminWebController::class, 'team'])->name('admin.team.index');
        Route::get('/create', [AdminWebController::class, 'createTeamMember'])->name('admin.team.create');
        Route::post('/', [AdminWebController::class, 'storeTeamMember'])->name('admin.team.store');
    });

    // Users Management
    Route::prefix('users')->group(function () {
        Route::get('/', [AdminWebController::class, 'users'])->name('admin.users.index');
        Route::delete('/bulk-delete', [AdminWebController::class, 'bulkDeleteUsers'])->name('admin.users.bulk-delete');
        Route::get('/{user}', [AdminWebController::class, 'showUser'])->name('admin.users.show');
        Route::get('/{user}/edit', [AdminWebController::class, 'editUser'])->name('admin.users.edit');
        Route::put('/{user}', [AdminWebController::class, 'updateUser'])->name('admin.users.update');
        Route::delete('/{user}', [AdminWebController::class, 'destroyUser'])->name('admin.users.destroy');
        Route::post('/{user}/ban', [AdminWebController::class, 'banUser'])->name('admin.users.ban');
        Route::post('/{user}/unban', [AdminWebController::class, 'unbanUser'])->name('admin.users.unban');
    });

    // Moderation
    Route::prefix('moderation')->group(function () {
        Route::get('/', [AdminWebController::class, 'moderation'])->name('admin.moderation.index');
        Route::get('/reports/{report}', [AdminWebController::class, 'showReport'])->name('admin.moderation.report');
        Route::post('/reports/{report}/resolve', [AdminWebController::class, 'resolveReport'])->name('admin.moderation.resolve');
        Route::post('/reports/{report}/dismiss', [AdminWebController::class, 'dismissReport'])->name('admin.moderation.dismiss');
        Route::delete('/reports/{report}/content', [AdminWebController::class, 'deleteReportedContent'])->name('admin.moderation.delete-content');
        Route::post('/reports/{report}/resolve-and-ban', [AdminWebController::class, 'resolveAndBan'])->name('admin.moderation.resolve-and-ban');
    });

    // Confessions
    Route::prefix('confessions')->group(function () {
        Route::get('/', [AdminWebController::class, 'confessions'])->name('admin.confessions.index');
        Route::get('/{confession}', [AdminWebController::class, 'showConfession'])->name('admin.confessions.show');
        Route::post('/{confession}/approve', [AdminWebController::class, 'approveConfession'])->name('admin.confessions.approve');
        Route::post('/{confession}/reject', [AdminWebController::class, 'rejectConfession'])->name('admin.confessions.reject');
        Route::delete('/{confession}', [AdminWebController::class, 'destroyConfession'])->name('admin.confessions.destroy');
    });

    // Payments
    Route::prefix('payments')->group(function () {
        Route::get('/', [AdminWebController::class, 'payments'])->name('admin.payments.index');
        Route::get('/{payment}', [AdminWebController::class, 'showPayment'])->name('admin.payments.show');
    });

    // Transactions (with CinetPay withdrawal validation)
    Route::prefix('transactions')->name('admin.transactions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\TransactionController::class, 'index'])->name('index');
        Route::get('/withdrawals/pending', [\App\Http\Controllers\Admin\TransactionController::class, 'pendingWithdrawals'])->name('withdrawals.pending');
        Route::get('/{transaction}', [\App\Http\Controllers\Admin\TransactionController::class, 'show'])->name('show');
        Route::post('/{transaction}/approve', [\App\Http\Controllers\Admin\TransactionController::class, 'approve'])->name('approve');
        Route::post('/{transaction}/reject', [\App\Http\Controllers\Admin\TransactionController::class, 'reject'])->name('reject');
    });

    // Messages
    Route::prefix('messages')->group(function () {
        Route::get('/', [AdminWebController::class, 'messages'])->name('admin.messages.index');
        Route::delete('/{message}', [AdminWebController::class, 'destroyMessage'])->name('admin.messages.destroy');
    });

    // Stories
    Route::prefix('stories')->group(function () {
        Route::get('/', [AdminWebController::class, 'stories'])->name('admin.stories.index');
        Route::delete('/{story}', [AdminWebController::class, 'destroyStory'])->name('admin.stories.destroy');
    });

    // Groups
    Route::prefix('groups')->group(function () {
        Route::get('/', [AdminWebController::class, 'groups'])->name('admin.groups.index');
        Route::get('/{group}', [AdminWebController::class, 'showGroup'])->name('admin.groups.show');
        Route::delete('/{group}', [AdminWebController::class, 'destroyGroup'])->name('admin.groups.destroy');
    });

    // Group Categories Management
    Route::resource('group-categories', \App\Http\Controllers\Admin\GroupCategoryController::class)
        ->names([
            'index' => 'admin.group-categories.index',
            'create' => 'admin.group-categories.create',
            'store' => 'admin.group-categories.store',
            'edit' => 'admin.group-categories.edit',
            'update' => 'admin.group-categories.update',
            'destroy' => 'admin.group-categories.destroy',
        ]);

    // Gifts
    Route::prefix('gifts')->group(function () {
        Route::get('/', [AdminWebController::class, 'gifts'])->name('admin.gifts.index');
    });

    // Gift Categories Management
    Route::resource('gift-categories', \App\Http\Controllers\Admin\GiftCategoryController::class)
        ->names([
            'index' => 'admin.gift-categories.index',
            'create' => 'admin.gift-categories.create',
            'store' => 'admin.gift-categories.store',
            'edit' => 'admin.gift-categories.edit',
            'update' => 'admin.gift-categories.update',
            'destroy' => 'admin.gift-categories.destroy',
        ]);

    // Gift Management
    Route::resource('gift-management', \App\Http\Controllers\Admin\GiftManagementController::class)
        ->parameters(['gift-management' => 'gift'])
        ->names([
            'index' => 'admin.gift-management.index',
            'create' => 'admin.gift-management.create',
            'store' => 'admin.gift-management.store',
            'edit' => 'admin.gift-management.edit',
            'update' => 'admin.gift-management.update',
            'destroy' => 'admin.gift-management.destroy',
        ]);

    // Withdrawals
    Route::prefix('withdrawals')->group(function () {
        Route::get('/', [AdminWebController::class, 'withdrawals'])->name('admin.withdrawals.index');
        Route::get('/export', [AdminWebController::class, 'exportWithdrawals'])->name('admin.withdrawals.export');
        Route::get('/{withdrawal}', [AdminWebController::class, 'showWithdrawal'])->name('admin.withdrawals.show');
        Route::post('/{withdrawal}/process', [AdminWebController::class, 'processWithdrawal'])->name('admin.withdrawals.process');
        Route::post('/{withdrawal}/reject', [AdminWebController::class, 'rejectWithdrawal'])->name('admin.withdrawals.reject');
    });
});

// Redirection vers le profil utilisateur (pour les liens partagés)
Route::get('/m/{userId}', function ($userId) {
    // Vérifier que l'utilisateur existe
    $user = \App\Models\User::where('id', $userId)
        ->where('is_banned', false)
        ->first();

    if (!$user) {
        abort(404, 'Utilisateur introuvable');
    }

    // Rediriger vers le frontend React
    $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:3000');
    return redirect("{$frontendUrl}/m/{$userId}");
})->name('user.profile');

// Page d'installation PWA
Route::get('/install', function () {
    return view('install');
})->name('pwa.install');

// Health check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});
