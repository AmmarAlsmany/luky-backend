<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoutingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceCategoryController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Controllers\UserRoleController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CustomerServiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\LanguageController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

require __DIR__ . '/auth.php';

// Language Switcher
Route::get('/language/{locale}', [LanguageController::class, 'switch'])->name('language.switch');

// Root/Home route (handles its own redirect)
Route::get('/', [RoutingController::class, 'index'])->name('root');

// Token refresh endpoint (temporary fix for existing sessions)
Route::post('/auth/refresh-token', [App\Http\Controllers\Auth\TokenRefreshController::class, 'refresh'])
    ->middleware('auth')
    ->name('auth.refresh-token');

// Protected routes - require authentication and active status
Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard routes
    Route::get('/dashboards/index', [DashboardController::class, 'index'])->name('dashboard.index');
    Route::get('/dashboards/admin', [DashboardController::class, 'admin'])->name('dashboard.admin')->middleware('role:admin|super_admin');
    Route::get('/dashboards/revenue-chart', [DashboardController::class, 'getRevenueChart'])->name('dashboard.revenue-chart');
    Route::get('/dashboards/bookings-chart', [DashboardController::class, 'getBookingsChart'])->name('dashboard.bookings-chart');
    Route::get('/dashboards/top-providers', [DashboardController::class, 'getTopProvidersApi'])->name('dashboard.top-providers');
    Route::get('/dashboards/recent-activities', [DashboardController::class, 'getRecentActivitiesApi'])->name('dashboard.recent-activities');

    // Client routes
    Route::get('/clients/list', [ClientController::class, 'index'])->name('clients.index')->middleware('permission:view_clients');
    Route::post('/clients/register', [ClientController::class, 'store'])->name('clients.store')->middleware('permission:create_clients');
    Route::get('/clients/export', [ClientController::class, 'export'])->name('clients.export')->middleware('permission:view_clients');
    Route::get('/clients/{id}/bookings', [ClientController::class, 'bookings'])->name('clients.bookings')->where('id', '[0-9]+')->middleware('permission:view_clients');
    Route::get('/clients/{id}/transactions', [ClientController::class, 'transactions'])->name('clients.transactions')->where('id', '[0-9]+')->middleware('permission:view_clients');
    Route::put('/clients/{id}', [ClientController::class, 'update'])->name('clients.update')->where('id', '[0-9]+')->middleware('permission:edit_clients');
    Route::put('/clients/{id}/status', [ClientController::class, 'updateStatus'])->name('clients.updateStatus')->where('id', '[0-9]+')->middleware('permission:edit_clients');
    Route::post('/clients/{id}/send-notification', [ClientController::class, 'sendNotification'])->name('clients.sendNotification')->where('id', '[0-9]+')->middleware('permission:send_notifications');
    Route::post('/clients/{id}/send-message', [ClientController::class, 'sendMessage'])->name('clients.sendMessage')->where('id', '[0-9]+')->middleware('permission:view_chat');
    Route::delete('/clients/{id}', [ClientController::class, 'destroy'])->name('clients.destroy')->where('id', '[0-9]+')->middleware('permission:delete_clients');
    Route::get('/clients/{id}', [ClientController::class, 'show'])->name('clients.show')->where('id', '[0-9]+')->middleware('permission:view_clients');

    // Provider routes
    Route::middleware(['permission:view_providers'])->group(function () {
        Route::get('/provider/list', [ProviderController::class, 'index'])->name('providers.index');
        Route::get('/provider/pending', [ProviderController::class, 'pending'])->name('providers.pending');
        Route::get('/provider/details/{id}', [ProviderController::class, 'show'])->name('providers.show');
        Route::get('/provider/export', [ProviderController::class, 'export'])->name('providers.export');
    });
    
    Route::get('/provider/create', [ProviderController::class, 'create'])->name('providers.create')->middleware('permission:create_providers');
    Route::post('/provider/store', [ProviderController::class, 'store'])->name('providers.store')->middleware('permission:create_providers');
    Route::put('/provider/{id}/status', [ProviderController::class, 'updateStatus'])->name('providers.updateStatus')->middleware('permission:edit_providers');
    Route::put('/provider/{id}/verify', [ProviderController::class, 'verify'])->name('providers.verify')->middleware('permission:edit_providers');
    Route::post('/provider/{id}/send-notification', [ProviderController::class, 'sendNotification'])->name('providers.sendNotification')->middleware('permission:send_notifications');
    Route::post('/provider/{id}/send-message', [ProviderController::class, 'sendMessage'])->name('providers.sendMessage')->middleware('permission:view_chat');
    Route::delete('/provider/{id}', [ProviderController::class, 'destroy'])->name('providers.destroy')->middleware('permission:delete_providers');
    Route::put('/provider/{providerId}/document/{documentId}/verify', [ProviderController::class, 'verifyDocument'])->name('providers.verifyDocument')->middleware('permission:edit_providers');

    // Booking routes
    Route::get('/bookings/list', [BookingController::class, 'index'])->name('bookings.index')->middleware('permission:view_bookings');
    Route::get('/bookings/export', [BookingController::class, 'export'])->name('bookings.export')->middleware('permission:export_bookings');
    Route::get('/bookings/{id}', [BookingController::class, 'show'])->name('bookings.show')->middleware('permission:view_bookings');
    Route::put('/bookings/{id}/status', [BookingController::class, 'updateStatus'])->name('bookings.updateStatus')->middleware('permission:edit_bookings');
    Route::post('/bookings/{id}/assign', [BookingController::class, 'assignProvider'])->name('bookings.assignProvider')->middleware('permission:edit_bookings');
    Route::delete('/bookings/{id}', [BookingController::class, 'destroy'])->name('bookings.destroy')->middleware('permission:cancel_bookings');

    // Service Category routes
    Route::middleware(['permission:manage_categories'])->group(function () {
        Route::get('/categories', [ServiceCategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [ServiceCategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [ServiceCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{id}', [ServiceCategoryController::class, 'show'])->name('categories.show');
        Route::get('/categories/{id}/edit', [ServiceCategoryController::class, 'edit'])->name('categories.edit');
        Route::put('/categories/{id}', [ServiceCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{id}', [ServiceCategoryController::class, 'destroy'])->name('categories.destroy');
        Route::post('/categories/{id}/toggle-status', [ServiceCategoryController::class, 'toggleStatus'])->name('categories.toggleStatus');
    });

    // Service routes
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
    Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
    Route::get('/services/search', [ServiceController::class, 'search'])->name('services.search');
    Route::get('/services/provider/{providerId}', [ServiceController::class, 'byProvider'])->name('services.byProvider');
    Route::get('/services/{id}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/services/{id}/edit', [ServiceController::class, 'edit'])->name('services.edit');
    Route::put('/services/{id}', [ServiceController::class, 'update'])->name('services.update');
    Route::delete('/services/{id}', [ServiceController::class, 'destroy'])->name('services.destroy');

    // Promo Code routes
    Route::get('/promos', [PromoCodeController::class, 'index'])->name('promos.index')->middleware('permission:view_promo_codes');
    Route::get('/promos/create', [PromoCodeController::class, 'create'])->name('promos.create')->middleware('permission:create_promo_codes');
    Route::post('/promos', [PromoCodeController::class, 'store'])->name('promos.store')->middleware('permission:create_promo_codes');
    Route::get('/promos/{id}', [PromoCodeController::class, 'show'])->name('promos.show')->where('id', '[0-9]+')->middleware('permission:view_promo_codes');
    Route::get('/promos/{id}/edit', [PromoCodeController::class, 'edit'])->name('promos.edit')->where('id', '[0-9]+')->middleware('permission:edit_promo_codes');
    Route::put('/promos/{id}', [PromoCodeController::class, 'update'])->name('promos.update')->where('id', '[0-9]+')->middleware('permission:edit_promo_codes');
    Route::post('/promos/{id}/toggle-status', [PromoCodeController::class, 'toggleStatus'])->name('promos.toggleStatus')->where('id', '[0-9]+')->middleware('permission:edit_promo_codes');
    Route::delete('/promos/{id}', [PromoCodeController::class, 'destroy'])->name('promos.destroy')->where('id', '[0-9]+')->middleware('permission:delete_promo_codes');

    // Users & Roles routes
    Route::get('/adminrole/users', [UserRoleController::class, 'users'])->name('adminrole.users')->middleware('permission:view_employees');
    Route::post('/adminrole/users', [UserRoleController::class, 'storeUser'])->name('adminrole.storeUser')->middleware('permission:create_employees');
    Route::post('/adminrole/users/{id}/toggle-status', [UserRoleController::class, 'toggleUserStatus'])->name('adminrole.toggleUserStatus')->where('id', '[0-9]+')->middleware('permission:edit_employees');
    Route::put('/adminrole/users/{id}/role', [UserRoleController::class, 'updateUserRole'])->name('adminrole.updateUserRole')->where('id', '[0-9]+')->middleware('permission:edit_employees');
    Route::post('/adminrole/users/{id}/reset-password', [UserRoleController::class, 'resetPassword'])->name('adminrole.resetPassword')->where('id', '[0-9]+')->middleware('permission:edit_employees');
    Route::delete('/adminrole/users/{id}', [UserRoleController::class, 'deleteUser'])->name('adminrole.deleteUser')->where('id', '[0-9]+')->middleware('permission:delete_employees');
    
    Route::get('/adminrole/list', [UserRoleController::class, 'roles'])->name('adminrole.roles')->middleware('permission:view_roles');
    Route::post('/adminrole/roles', [UserRoleController::class, 'storeRole'])->name('adminrole.storeRole')->middleware('permission:create_roles');
    Route::get('/adminrole/edit/{id}', [UserRoleController::class, 'editRole'])->name('adminrole.editRole')->where('id', '[0-9]+')->middleware('permission:edit_roles');
    Route::put('/adminrole/{id}', [UserRoleController::class, 'updateRole'])->name('adminrole.updateRole')->where('id', '[0-9]+')->middleware('permission:assign_permissions');

    // Reviews & Ratings routes
    Route::get('/reviews/list', [ReviewController::class, 'index'])->name('reviews.index')->middleware('permission:view_reviews');
    Route::get('/reviews/provider/{id}', [ReviewController::class, 'show'])->name('reviews.show')->where('id', '[0-9]+')->middleware('permission:view_reviews');
    Route::post('/reviews/{id}/toggle-flag', [ReviewController::class, 'toggleFlag'])->name('reviews.toggleFlag')->where('id', '[0-9]+')->middleware('permission:hide_reviews');
    Route::post('/reviews/{id}/response', [ReviewController::class, 'updateResponse'])->name('reviews.updateResponse')->where('id', '[0-9]+')->middleware('permission:view_reviews');
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy'])->name('reviews.destroy')->where('id', '[0-9]+')->middleware('permission:delete_reviews');

    // Notifications routes
    Route::get('/notifications/list', [NotificationController::class, 'index'])->name('notifications.index')->middleware('permission:view_notifications');
    Route::post('/notifications/send', [NotificationController::class, 'send'])->name('notifications.send')->middleware('permission:send_notifications');
    Route::post('/notifications/broadcast', [NotificationController::class, 'broadcast'])->name('notifications.broadcast')->middleware('permission:send_notifications');
    Route::get('/notifications/users/{audience}', [NotificationController::class, 'getUsersByAudience'])->name('notifications.getUsersByAudience')->middleware('permission:send_notifications');
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead')->where('id', '[0-9]+')->middleware('permission:view_notifications');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead')->middleware('permission:view_notifications');

    // Payment Management routes
    Route::get('/payments/transactions', [PaymentController::class, 'transactions'])->name('payments.transactions')->middleware('permission:view_payments');
    Route::get('/payments/methods', [PaymentController::class, 'methods'])->name('payments.methods')->middleware('permission:view_payments');
    Route::get('/payments/commissions', [PaymentController::class, 'commissions'])->name('payments.commissions')->middleware('permission:view_payments');
    Route::get('/payments/export', [PaymentController::class, 'exportTransactions'])->name('payments.export')->middleware('permission:view_payments');
    Route::get('/payments/test-connection', [PaymentController::class, 'testConnection'])->name('payments.testConnection')->middleware('permission:manage_payment_settings');
    Route::get('/payments/{id}/details', [PaymentController::class, 'getTransactionDetails'])->name('payments.details')->middleware('permission:view_payments');
    Route::get('/payments/{id}/receipt', [PaymentController::class, 'viewReceipt'])->name('payments.receipt')->middleware('permission:view_payments');
    Route::get('/payments/{id}/receipt/download', [PaymentController::class, 'downloadReceipt'])->name('payments.receipt.download')->middleware('permission:view_payments');
    Route::post('/payments/provider/update-settings', [PaymentController::class, 'updateProviderSettings'])->name('payments.updateProviderSettings')->middleware('permission:manage_payment_settings');
    Route::post('/payments/gateway/update-settings', [PaymentController::class, 'updateGatewaySettings'])->name('payments.updateGatewaySettings')->middleware('permission:manage_payment_settings');
    Route::post('/payments/methods/toggle', [PaymentController::class, 'togglePaymentMethod'])->name('payments.methods.toggle')->middleware('permission:manage_payment_settings');
    Route::post('/payments/methods/select', [PaymentController::class, 'selectPaymentMethod'])->name('payments.methods.select')->middleware('permission:manage_payment_settings');

    // Payment Settings (Gateway Configuration) - Using RoutingController for now
    // Route handled by fallback secondLevel route

    // Banner Management routes
    Route::middleware(['permission:manage_banners'])->group(function () {
        Route::get('/banners/banners', [BannerController::class, 'index'])->name('banners.index');
        Route::post('/banners/store', [BannerController::class, 'store'])->name('banners.store');
        Route::get('/banners/{id}', [BannerController::class, 'show'])->name('banners.show')->where('id', '[0-9]+');
        Route::delete('/banners/{id}', [BannerController::class, 'destroy'])->name('banners.destroy')->where('id', '[0-9]+');
    });

    // Reports routes
    Route::get('/reports/reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:view_reports');
    Route::get('/reports/revenue', [ReportController::class, 'revenueReport'])->name('reports.revenue')->middleware('permission:view_reports');
    Route::get('/reports/bookings', [ReportController::class, 'bookingStats'])->name('reports.bookings')->middleware('permission:view_reports');
    Route::get('/reports/providers', [ReportController::class, 'providerPerformance'])->name('reports.providers')->middleware('permission:view_reports');
    Route::get('/reports/clients', [ReportController::class, 'clientSpending'])->name('reports.clients')->middleware('permission:view_reports');
    Route::get('/reports/commission', [ReportController::class, 'commissionReport'])->name('reports.commission')->middleware('permission:view_reports');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export')->middleware('permission:export_reports');
    
    // Settings routes
    Route::get('/settings/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/update', [SettingsController::class, 'updateSettings'])->name('settings.update')->middleware('permission:manage_general_settings');
    Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');

    // Customer Services routes
    Route::get('/customerservices/tickets', [CustomerServiceController::class, 'tickets'])->name('customerservices.tickets')->middleware('permission:view_tickets');
    Route::get('/customerservices/tickets/{id}', [CustomerServiceController::class, 'showTicket'])->name('customerservices.showTicket')->where('id', '[0-9]+')->middleware('permission:view_tickets');
    Route::post('/customerservices/tickets', [CustomerServiceController::class, 'storeTicket'])->name('customerservices.storeTicket')->middleware('permission:create_tickets');
    Route::put('/customerservices/tickets/{id}/status', [CustomerServiceController::class, 'updateTicketStatus'])->name('customerservices.updateTicketStatus')->where('id', '[0-9]+')->middleware('permission:close_tickets');
    Route::put('/customerservices/tickets/{id}/assign', [CustomerServiceController::class, 'assignTicket'])->name('customerservices.assignTicket')->where('id', '[0-9]+')->middleware('permission:assign_tickets');
    Route::post('/customerservices/tickets/{id}/message', [CustomerServiceController::class, 'addTicketMessage'])->name('customerservices.addTicketMessage')->where('id', '[0-9]+')->middleware('permission:view_tickets');
    
    Route::get('/customerservices/chat', [CustomerServiceController::class, 'chat'])->name('customerservices.chat')->middleware('permission:view_chat');
    Route::get('/customerservices/chat/{id}/messages', [CustomerServiceController::class, 'getConversationMessages'])->name('customerservices.getConversationMessages')->where('id', '[0-9]+')->middleware('permission:view_chat');
    Route::post('/customerservices/chat/{id}/send', [CustomerServiceController::class, 'sendConversationMessage'])->name('customerservices.sendConversationMessage')->where('id', '[0-9]+')->middleware('permission:view_chat');
    Route::post('/customerservices/chat/{id}/mark-read', [CustomerServiceController::class, 'markConversationAsRead'])->name('customerservices.markConversationAsRead')->where('id', '[0-9]+')->middleware('permission:view_chat');
    Route::post('/customerservices/chat/create', [CustomerServiceController::class, 'createConversation'])->name('customerservices.createConversation')->middleware('permission:view_chat');
    
    Route::get('/customerservices/stats', [CustomerServiceController::class, 'stats'])->name('customerservices.stats')->middleware('permission:view_tickets');

    // Static Pages (Content Management) routes
    Route::middleware(['permission:manage_general_settings'])->group(function () {
        Route::get('/content/pages', [\App\Http\Controllers\StaticPagesController::class, 'index'])->name('static-pages.index');
        Route::get('/content/pages/create', [\App\Http\Controllers\StaticPagesController::class, 'create'])->name('static-pages.create');
        Route::post('/content/pages', [\App\Http\Controllers\StaticPagesController::class, 'store'])->name('static-pages.store');
        Route::get('/content/pages/{id}/edit', [\App\Http\Controllers\StaticPagesController::class, 'edit'])->name('static-pages.edit');
        Route::put('/content/pages/{id}', [\App\Http\Controllers\StaticPagesController::class, 'update'])->name('static-pages.update');
        Route::post('/content/pages/{id}/toggle-status', [\App\Http\Controllers\StaticPagesController::class, 'toggleStatus'])->name('static-pages.toggleStatus');
        Route::delete('/content/pages/{id}', [\App\Http\Controllers\StaticPagesController::class, 'destroy'])->name('static-pages.destroy');
    });

    // Debug route - assign admin role
    Route::get('/debug/assign-admin', function() {
        $user = auth()->user();
        if (!$user) {
            return 'Not logged in';
        }
        
        $user->assignRole('admin');
        return 'Admin role assigned to ' . $user->name . ' (ID: ' . $user->id . '). Current roles: ' . $user->getRoleNames()->implode(', ');
    });

    // Ignore Chrome DevTools requests
    Route::get('.well-known/{any}', function() {
        abort(404);
    })->where('any', '.*');

    // Fallback routes for existing pages (still using RoutingController)
    Route::get('{first}/{second}/{third}', [RoutingController::class, 'thirdLevel'])
        ->where('first', '^(?!images|css|js|fonts|assets).*$')
        ->name('third');
    Route::get('{first}/{second}', [RoutingController::class, 'secondLevel'])
        ->where('first', '^(?!images|css|js|fonts|assets).*$')
        ->name('second');
    Route::get('{any}', [RoutingController::class, 'root'])
        ->where('any', '^(?!images|css|js|fonts|assets).*$')
        ->name('any');
});
