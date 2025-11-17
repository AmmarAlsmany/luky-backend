<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\OtpController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProviderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\PromoCodeController;

// Public routes - No authentication required
Route::prefix('v1')->group(function () {

    // Authentication (Rate Limited)
    Route::post('/auth/send-otp', [OtpController::class, 'sendOtp'])->middleware('throttle:5,1');
    Route::post('/auth/verify-otp', [OtpController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::post('/auth/resend-otp', [OtpController::class, 'resendOtp'])->middleware('throttle:3,1');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    // Public Data APIs
    Route::get('/cities', [LocationController::class, 'cities']);
    Route::get('/cities/search', [LocationController::class, 'searchCities']);
    Route::get('/cities/{id}', [LocationController::class, 'cityById']);
    Route::get('/app-settings', [LocationController::class, 'appSettings']);
    Route::get('/banners', [LocationController::class, 'banners']);

    // Service Categories
    Route::get('/service-categories', [ServiceController::class, 'categories']);

    // Provider Discovery
    Route::get('/providers', [ServiceController::class, 'providers']);
    Route::get('/providers/{id}', [ServiceController::class, 'providerDetails']);
    Route::get('/search', [ServiceController::class, 'search']);

    // Provider Reviews (Public)
    Route::get('/providers/{id}/reviews', [ReviewController::class, 'getProviderReviews']);

    // Advanced Service Search & Filters (Public)
    Route::get('/services', [ServiceController::class, 'getAllServices']);
    Route::get('/services/search', [ServiceController::class, 'searchServices']);
    Route::get('/services/trending', [ServiceController::class, 'trendingServices']);
    Route::get('/services/home-available', [ServiceController::class, 'homeServices']);
    Route::get('/services/by-category', [ServiceController::class, 'servicesGroupedByCategory']);
    Route::get('/categories/{id}/popular-services', [ServiceController::class, 'popularServicesByCategory']);
    Route::get('/categories/{id}/price-range', [ServiceController::class, 'categoryPriceRange']);

    // Static Pages (Public)
    Route::get('/pages/{slug}', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'getBySlug']);
});

// Protected routes - Requires authentication, active status, and correct app type
Route::prefix('v1')->middleware(['auth:sanctum', 'active', 'validate.app.type'])->group(function () {

    // User Profile Management
    Route::get('/user/profile', [AuthController::class, 'profile']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::delete('/user/account', [AuthController::class, 'deleteAccount']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Provider Registration & Management
    Route::middleware(['role:client|provider|admin|super_admin'])->group(function () {
        Route::post('/provider/register', [ProviderController::class, 'registerProvider']);
    });

    Route::middleware(['role:provider'])->group(function () {
        // Profile
        Route::get('/provider/profile', [ProviderController::class, 'getProfile']);
        Route::put('/provider/profile', [ProviderController::class, 'updateProfile']);

        // Documents
        Route::post('/provider/documents', [ProviderController::class, 'uploadDocuments']);

        // Media
        Route::post('/provider/gallery', [ProviderController::class, 'uploadGallery']);
        Route::post('/provider/logo', [ProviderController::class, 'uploadLogo']);
        Route::post('/provider/building-image', [ProviderController::class, 'uploadBuildingImage']);

        // Services
        Route::get('/provider/services', [ProviderController::class, 'getServices']);
        Route::post('/provider/services', [ProviderController::class, 'createService']);
        Route::put('/provider/services/{id}', [ProviderController::class, 'updateService']);
        Route::delete('/provider/services/{id}', [ProviderController::class, 'deleteService']);

        // Analytics
        Route::get('/provider/analytics', [ProviderController::class, 'getAnalytics']);
    });

    // Client booking routes
    Route::middleware(['role:client'])->group(function () {
        Route::post('/bookings', [BookingController::class, 'store']);
        Route::get('/bookings', [BookingController::class, 'clientBookings']);
        Route::get('/bookings/{id}', [BookingController::class, 'show']);
        Route::get('/bookings/{id}/cancellation-preview', [BookingController::class, 'previewCancellation']);
        Route::put('/bookings/{id}/cancel', [BookingController::class, 'cancelBooking']);
    });

    // Provider booking routes
    Route::middleware(['role:provider'])->group(function () {
        Route::get('/provider/bookings', [BookingController::class, 'providerBookings']);
        Route::put('/provider/bookings/{id}/accept', [BookingController::class, 'acceptBooking']);
        Route::put('/provider/bookings/{id}/reject', [BookingController::class, 'rejectBooking']);
        Route::get('/provider/schedule', [BookingController::class, 'providerSchedule']);
    });

    // Client review routes
    Route::middleware(['role:client'])->group(function () {
        Route::post('/bookings/{id}/review', [ReviewController::class, 'submitReview']);
        Route::get('/my-reviews', [ReviewController::class, 'getMyReviews']);
    });

    // Provider review routes
    Route::middleware(['role:provider'])->group(function () {
        Route::get('/provider/reviews', [ReviewController::class, 'getReceivedReviews']);
    });

    // Client payment routes (Rate Limited)
    Route::middleware(['role:client'])->group(function () {
        Route::get('/payments/methods', [PaymentController::class, 'getPaymentMethods']);
        Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment'])->middleware('throttle:10,1');
        Route::post('/payments/wallet', [PaymentController::class, 'payWithWallet'])->middleware('throttle:10,1');
    });

    // Notification routes (all authenticated users)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('/notifications/read-all', [NotificationController::class, 'deleteAllRead']);

    // Device token management
    Route::post('/device-token', [NotificationController::class, 'registerDeviceToken']);
    Route::delete('/device-token', [NotificationController::class, 'removeDeviceToken']);
    Route::get('/devices', [NotificationController::class, 'getDevices']);

    // TEST: Send test notification
    Route::post('/notifications/test', [NotificationController::class, 'sendTestNotification']);

    // Send message to admin
    Route::post('/notifications/send-to-admin', [NotificationController::class, 'sendMessageToAdmin']);

    // Get admin conversation messages
    Route::get('/notifications/admin-messages', [NotificationController::class, 'getAdminMessages']);

    // User Address routes (all authenticated users)
    Route::get('/addresses', [UserAddressController::class, 'index']);
    Route::post('/addresses', [UserAddressController::class, 'store']);
    Route::get('/addresses/{id}', [UserAddressController::class, 'show']);
    Route::put('/addresses/{id}', [UserAddressController::class, 'update']);
    Route::delete('/addresses/{id}', [UserAddressController::class, 'destroy']);
    Route::post('/addresses/{id}/set-default', [UserAddressController::class, 'setDefault']);

    // Promo Code routes (all authenticated users)
    Route::post('/promo-codes/validate', [PromoCodeController::class, 'validatePromoCode']);

    // Chat routes (all authenticated users - client and provider)
    Route::get('/conversations', [ChatController::class, 'getConversations']);
    Route::post('/conversations', [ChatController::class, 'startConversation']);
    Route::get('/conversations/{conversationId}/messages', [ChatController::class, 'getMessages']);
    Route::post('/conversations/{conversationId}/messages', [ChatController::class, 'sendMessage']);
    Route::post('/conversations/{conversationId}/images', [ChatController::class, 'sendImage']);
    Route::put('/conversations/{conversationId}/read', [ChatController::class, 'markConversationAsRead']);
    Route::put('/messages/{messageId}/read', [ChatController::class, 'markMessageAsRead']);
    Route::post('/conversations/{conversationId}/typing', [ChatController::class, 'sendTypingIndicator']);
    Route::get('/chat/unread-count', [ChatController::class, 'getUnreadCount']);

    // Favorites routes
    Route::get('/favorites', [App\Http\Controllers\Api\FavoritesController::class, 'index']);
    Route::post('/favorites', [App\Http\Controllers\Api\FavoritesController::class, 'store']);
    Route::delete('/favorites/{providerId}', [App\Http\Controllers\Api\FavoritesController::class, 'destroy']);
    Route::get('/favorites/check/{providerId}', [App\Http\Controllers\Api\FavoritesController::class, 'check']);
    Route::post('/favorites/toggle', [App\Http\Controllers\Api\FavoritesController::class, 'toggle']);
});

// Payment webhook (no auth - MyFatoorah calls this endpoint server-to-server)
Route::post('/v1/payments/webhook', [PaymentController::class, 'webhook']);

// ============================================
// ADMIN DASHBOARD APIs
// ============================================
Route::prefix('admin')->group(function () {

    // Public admin routes (no authentication, rate limited)
    Route::post('/auth/login', [App\Http\Controllers\Api\Admin\AdminAuthController::class, 'login'])->middleware('throttle:5,1');

    // Protected admin routes (requires authentication + dashboard role + active status)
    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        // Admin authentication endpoints
        Route::get('/auth/profile', [App\Http\Controllers\Api\Admin\AdminAuthController::class, 'profile']);
        Route::put('/auth/profile', [App\Http\Controllers\Api\Admin\AdminAuthController::class, 'updateProfile']);
        Route::put('/auth/change-password', [App\Http\Controllers\Api\Admin\AdminAuthController::class, 'changePassword']);
        Route::post('/auth/logout', [App\Http\Controllers\Api\Admin\AdminAuthController::class, 'logout']);
        Route::post('/auth/logout-all', [App\Http\Controllers\Api\Admin\AdminAuthController::class, 'logoutAll']);

        // ===================================
        // CLIENT MANAGEMENT
        // ===================================
        Route::get('/clients', [App\Http\Controllers\Api\Admin\ClientController::class, 'index']);
        Route::post('/clients/register', [App\Http\Controllers\Api\Admin\ClientController::class, 'store']);
        Route::get('/clients/stats', [App\Http\Controllers\Api\Admin\ClientController::class, 'stats']);
        Route::get('/clients/export', [App\Http\Controllers\Api\Admin\ClientController::class, 'export']);
        Route::get('/clients/{id}', [App\Http\Controllers\Api\Admin\ClientController::class, 'show']);
        Route::put('/clients/{id}', [App\Http\Controllers\Api\Admin\ClientController::class, 'update']);
        Route::delete('/clients/{id}', [App\Http\Controllers\Api\Admin\ClientController::class, 'destroy']);
        Route::put('/clients/{id}/status', [App\Http\Controllers\Api\Admin\ClientController::class, 'updateStatus']);
        Route::get('/clients/{id}/bookings', [App\Http\Controllers\Api\Admin\ClientController::class, 'bookings']);
        Route::get('/clients/{id}/transactions', [App\Http\Controllers\Api\Admin\ClientController::class, 'transactions']);

        // ===================================
        // PROVIDER MANAGEMENT
        // ===================================
        Route::get('/providers', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'index']);
        Route::get('/providers/stats', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'stats']);
        Route::get('/providers/pending-approval', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'pendingApproval']);
        Route::get('/providers/export', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'export']);
        Route::get('/providers/{id}', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'show']);
        Route::put('/providers/{id}', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'update']);
        Route::delete('/providers/{id}', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'destroy']);
        Route::put('/providers/{id}/verify', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'verifyProvider']);
        Route::put('/providers/{id}/status', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'updateStatus']);
        Route::get('/providers/{id}/services', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'services']);
        Route::get('/providers/{id}/bookings', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'bookings']);
        Route::get('/providers/{id}/revenue', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'revenue']);
        Route::get('/providers/{id}/reviews', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'reviews']);
        Route::get('/providers/{id}/activity-logs', [App\Http\Controllers\Api\Admin\ProviderManagementController::class, 'activityLogs']);

        // ===================================
        // EMPLOYEE MANAGEMENT
        // ===================================
        Route::get('/employees', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'index']);
        Route::get('/employees/stats', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'stats']);
        Route::get('/employees/export', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'export']);
        Route::post('/employees', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'store']);
        Route::get('/employees/{id}', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'show']);
        Route::put('/employees/{id}', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'destroy']);
        Route::put('/employees/{id}/status', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'updateStatus']);
        Route::put('/employees/{id}/role', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'assignRole']);
        Route::put('/employees/{id}/permissions', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'updatePermissions']);
        Route::post('/employees/{id}/reset-password', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'resetPassword']);

        // Roles & Permissions
        Route::get('/roles', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'getRoles']);
        Route::get('/permissions', [App\Http\Controllers\Api\Admin\EmployeeController::class, 'getPermissions']);

        // ===================================
        // PAYMENT SETTINGS
        // ===================================
        // Payment Gateways
        Route::get('/payment-gateways', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'getGateways']);
        Route::get('/payment-gateways/{id}', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'getGateway']);
        Route::post('/payment-gateways', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'createGateway']);
        Route::put('/payment-gateways/{id}', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'updateGateway']);
        Route::delete('/payment-gateways/{id}', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'deleteGateway']);
        Route::post('/payment-gateways/{id}/toggle', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'toggleGateway']);
        Route::post('/payment-gateways/{id}/test', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'testGatewayConnection']);

        // Payment Settings
        Route::get('/payment-settings', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'getSettings']);
        Route::put('/payment-settings', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'updateSettings']);

        // Tax Settings
        Route::get('/payment-settings/tax', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'getTaxSettings']);
        Route::put('/payment-settings/tax', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'updateTaxSettings']);

        // Commission Settings
        Route::get('/payment-settings/commission', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'getCommissionSettings']);
        Route::put('/payment-settings/commission', [App\Http\Controllers\Api\Admin\PaymentSettingsController::class, 'updateCommissionSettings']);

        // ===================================
        // REPORTS & ANALYTICS
        // ===================================
        Route::get('/reports/revenue/overview', [App\Http\Controllers\Api\Admin\ReportsController::class, 'revenueOverview']);
        Route::get('/reports/revenue/by-period', [App\Http\Controllers\Api\Admin\ReportsController::class, 'revenueByPeriod']);
        Route::get('/reports/bookings/statistics', [App\Http\Controllers\Api\Admin\ReportsController::class, 'bookingStatistics']);
        Route::get('/reports/providers/revenue', [App\Http\Controllers\Api\Admin\ReportsController::class, 'providerRevenueReport']);
        Route::get('/reports/clients/spending', [App\Http\Controllers\Api\Admin\ReportsController::class, 'clientSpendingReport']);
        Route::get('/reports/commission', [App\Http\Controllers\Api\Admin\ReportsController::class, 'commissionReport']);
        Route::get('/reports/payment-methods', [App\Http\Controllers\Api\Admin\ReportsController::class, 'paymentMethodsStats']);
        Route::get('/reports/revenue/export', [App\Http\Controllers\Api\Admin\ReportsController::class, 'exportRevenueReport']);
        Route::get('/reports/bookings/export', [App\Http\Controllers\Api\Admin\ReportsController::class, 'exportBookingsReport']);

        // ===================================
        // PROMO CODES
        // ===================================
        Route::get('/promo-codes', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'index']);
        Route::get('/promo-codes/stats', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'stats']);
        Route::get('/promo-codes/generate', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'generateCode']);
        Route::post('/promo-codes/validate', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'validate']);
        Route::get('/promo-codes/{id}', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'show']);
        Route::post('/promo-codes', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'store']);
        Route::put('/promo-codes/{id}', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'update']);
        Route::delete('/promo-codes/{id}', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'destroy']);
        Route::post('/promo-codes/{id}/toggle', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'toggleStatus']);
        Route::get('/promo-codes/{id}/usage', [App\Http\Controllers\Api\Admin\PromoCodesController::class, 'usageHistory']);

        // ===================================
        // BANNERS & ADS
        // ===================================
        Route::get('/banners', [App\Http\Controllers\Api\Admin\BannersController::class, 'index']);
        Route::get('/banners/stats', [App\Http\Controllers\Api\Admin\BannersController::class, 'stats']);
        Route::get('/banners/{id}', [App\Http\Controllers\Api\Admin\BannersController::class, 'show']);
        Route::post('/banners', [App\Http\Controllers\Api\Admin\BannersController::class, 'store']);
        Route::put('/banners/{id}', [App\Http\Controllers\Api\Admin\BannersController::class, 'update']);
        Route::delete('/banners/{id}', [App\Http\Controllers\Api\Admin\BannersController::class, 'destroy']);
        Route::post('/banners/{id}/toggle', [App\Http\Controllers\Api\Admin\BannersController::class, 'toggleStatus']);
        Route::put('/banners/order', [App\Http\Controllers\Api\Admin\BannersController::class, 'updateOrder']);
        Route::get('/banners/{id}/analytics', [App\Http\Controllers\Api\Admin\BannersController::class, 'analytics']);

        // ===================================
        // STATIC PAGES
        // ===================================
        Route::get('/pages', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'index']);
        Route::get('/pages/stats', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'stats']);
        Route::get('/pages/{id}', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'show']);
        Route::post('/pages', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'store']);
        Route::put('/pages/{id}', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'update']);
        Route::delete('/pages/{id}', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'destroy']);
        Route::post('/pages/{id}/toggle', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'toggleStatus']);
        Route::post('/pages/{id}/duplicate', [App\Http\Controllers\Api\Admin\StaticPagesController::class, 'duplicate']);

        // ===================================
        // ADMIN NOTIFICATIONS
        // ===================================
        Route::get('/notifications', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'index']);
        Route::get('/notifications/stats', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'stats']);
        Route::get('/notifications/scheduled', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'scheduled']);
        Route::get('/notifications/templates', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'templates']);
        Route::get('/notifications/user-counts', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'userCounts']);
        Route::get('/notifications/{id}', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'show']);
        Route::post('/notifications/send', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'send']);
        Route::post('/notifications/send-test', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'sendTest']);
        Route::delete('/notifications/{id}', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'destroy']);
        Route::delete('/notifications/{id}/cancel', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'cancelScheduled']);

        // ===================================
        // ADMIN MESSAGING
        // ===================================
        Route::post('/messages/send', [App\Http\Controllers\Api\Admin\NotificationsController::class, 'sendMessage']);

        // ===================================
        // REVIEWS MANAGEMENT
        // ===================================
        Route::get('/reviews', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'index']);
        Route::get('/reviews/stats', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'stats']);
        Route::get('/reviews/flagged', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'flagged']);
        Route::get('/reviews/{id}', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'show']);
        Route::post('/reviews/{id}/flag', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'flag']);
        Route::post('/reviews/{id}/unflag', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'unflag']);
        Route::post('/reviews/{id}/toggle-visibility', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'toggleVisibility']);
        Route::post('/reviews/{id}/respond', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'respond']);
        Route::delete('/reviews/{id}/response', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'deleteResponse']);
        Route::delete('/reviews/{id}', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'destroy']);
        Route::get('/reviews/provider/{providerId}', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'byProvider']);
        Route::get('/reviews/user/{userId}', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'byUser']);
        Route::post('/reviews/bulk-action', [App\Http\Controllers\Api\Admin\ReviewsController::class, 'bulkAction']);

        // ===================================
        // SUPPORT & TICKETING
        // ===================================
        Route::get('/support/tickets', [App\Http\Controllers\Api\Admin\SupportController::class, 'index']);
        Route::get('/support/tickets/stats', [App\Http\Controllers\Api\Admin\SupportController::class, 'stats']);
        Route::get('/support/tickets/{id}', [App\Http\Controllers\Api\Admin\SupportController::class, 'show']);
        Route::put('/support/tickets/{id}', [App\Http\Controllers\Api\Admin\SupportController::class, 'update']);
        Route::post('/support/tickets/{id}/assign', [App\Http\Controllers\Api\Admin\SupportController::class, 'assign']);
        Route::post('/support/tickets/{id}/messages', [App\Http\Controllers\Api\Admin\SupportController::class, 'addMessage']);
        Route::delete('/support/tickets/{id}', [App\Http\Controllers\Api\Admin\SupportController::class, 'destroy']);
        Route::get('/support/agents', [App\Http\Controllers\Api\Admin\SupportController::class, 'getAgents']);

        // Canned Responses
        Route::get('/support/canned-responses', [App\Http\Controllers\Api\Admin\SupportController::class, 'getCannedResponses']);
        Route::post('/support/canned-responses', [App\Http\Controllers\Api\Admin\SupportController::class, 'createCannedResponse']);
        Route::put('/support/canned-responses/{id}', [App\Http\Controllers\Api\Admin\SupportController::class, 'updateCannedResponse']);
        Route::delete('/support/canned-responses/{id}', [App\Http\Controllers\Api\Admin\SupportController::class, 'deleteCannedResponse']);

        // ===================================
        // GENERAL SETTINGS
        // ===================================
        Route::get('/settings', [App\Http\Controllers\Api\Admin\SettingsController::class, 'index']);
        Route::get('/settings/{key}', [App\Http\Controllers\Api\Admin\SettingsController::class, 'show']);
        Route::put('/settings/{key}', [App\Http\Controllers\Api\Admin\SettingsController::class, 'update']);
        Route::post('/settings/bulk-update', [App\Http\Controllers\Api\Admin\SettingsController::class, 'bulkUpdate']);

        // App Settings
        Route::get('/settings/app/general', [App\Http\Controllers\Api\Admin\SettingsController::class, 'getAppSettings']);
        Route::put('/settings/app/general', [App\Http\Controllers\Api\Admin\SettingsController::class, 'updateAppSettings']);

        // Booking Settings
        Route::get('/settings/booking', [App\Http\Controllers\Api\Admin\SettingsController::class, 'getBookingSettings']);
        Route::put('/settings/booking', [App\Http\Controllers\Api\Admin\SettingsController::class, 'updateBookingSettings']);

        // Notification Settings
        Route::get('/settings/notifications', [App\Http\Controllers\Api\Admin\SettingsController::class, 'getNotificationSettings']);
        Route::put('/settings/notifications', [App\Http\Controllers\Api\Admin\SettingsController::class, 'updateNotificationSettings']);

        // Maintenance Mode
        Route::get('/settings/maintenance', [App\Http\Controllers\Api\Admin\SettingsController::class, 'getMaintenanceMode']);
        Route::post('/settings/maintenance/toggle', [App\Http\Controllers\Api\Admin\SettingsController::class, 'toggleMaintenanceMode']);

        // Cache Management
        Route::post('/settings/cache/clear', [App\Http\Controllers\Api\Admin\SettingsController::class, 'clearCache']);

        // ===================================
        // DASHBOARD ANALYTICS & KPIs
        // ===================================
        Route::get('/dashboard', [App\Http\Controllers\Api\Admin\DashboardController::class, 'index']);
        Route::get('/dashboard/overview', [App\Http\Controllers\Api\Admin\DashboardController::class, 'overview']);
        Route::get('/dashboard/charts/revenue', [App\Http\Controllers\Api\Admin\DashboardController::class, 'revenueChart']);
        Route::get('/dashboard/charts/bookings', [App\Http\Controllers\Api\Admin\DashboardController::class, 'bookingsChart']);
        Route::get('/dashboard/charts/users-growth', [App\Http\Controllers\Api\Admin\DashboardController::class, 'usersGrowthChart']);
        Route::get('/dashboard/top-providers', [App\Http\Controllers\Api\Admin\DashboardController::class, 'topProviders']);
        Route::get('/dashboard/recent-activities', [App\Http\Controllers\Api\Admin\DashboardController::class, 'recentActivities']);
        Route::get('/dashboard/booking-status-distribution', [App\Http\Controllers\Api\Admin\DashboardController::class, 'bookingStatusDistribution']);
        Route::get('/dashboard/rating-distribution', [App\Http\Controllers\Api\Admin\DashboardController::class, 'ratingDistribution']);
    });
});