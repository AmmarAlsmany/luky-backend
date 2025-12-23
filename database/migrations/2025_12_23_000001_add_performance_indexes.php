<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add indexes for performance optimization
     */
    public function up(): void
    {
        // Bookings table indexes
        Schema::table('bookings', function (Blueprint $table) {
            // Index for provider bookings queries
            $table->index(['provider_id', 'status', 'booking_date'], 'idx_bookings_provider_status_date');

            // Index for client bookings queries
            $table->index(['client_id', 'status', 'created_at'], 'idx_bookings_client_status_created');

            // Index for payment status queries
            $table->index(['payment_status', 'status'], 'idx_bookings_payment_status');

            // Index for payment deadline (for auto-cancellation job)
            $table->index(['payment_deadline', 'status'], 'idx_bookings_payment_deadline');

            // Index for analytics queries
            $table->index(['status', 'created_at'], 'idx_bookings_status_created');
        });

        // Services table indexes
        Schema::table('services', function (Blueprint $table) {
            // Index for provider services
            $table->index(['provider_id', 'is_active'], 'idx_services_provider_active');

            // Index for category queries
            $table->index(['category_id', 'is_active', 'price'], 'idx_services_category_active_price');

            // Index for home service filtering
            $table->index(['available_at_home', 'is_active'], 'idx_services_home_active');

            // Index for price range queries
            $table->index(['is_active', 'price'], 'idx_services_active_price');
        });

        // Service providers table indexes
        Schema::table('service_providers', function (Blueprint $table) {
            // Index for active approved providers
            $table->index(['verification_status', 'is_active'], 'idx_providers_status_active');

            // Index for city filtering
            $table->index(['city_id', 'is_active', 'verification_status'], 'idx_providers_city_active_verified');

            // Index for featured providers
            $table->index(['is_featured', 'average_rating'], 'idx_providers_featured_rating');

            // Index for business type filtering
            $table->index(['business_type', 'is_active'], 'idx_providers_type_active');

            // Index for location-based queries
            $table->index(['latitude', 'longitude'], 'idx_providers_location');
        });

        // Booking items table indexes
        Schema::table('booking_items', function (Blueprint $table) {
            // Index for booking items queries
            $table->index(['booking_id', 'service_id'], 'idx_booking_items_booking_service');

            // Index for service popularity queries
            $table->index(['service_id'], 'idx_booking_items_service');
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            // Index for user type queries
            $table->index(['user_type', 'status'], 'idx_users_type_status');

            // Index for city filtering
            $table->index(['city_id', 'user_type'], 'idx_users_city_type');

            // Index for phone lookup (if not already unique)
            if (!Schema::hasColumn('users', 'phone_index')) {
                $table->index(['phone'], 'idx_users_phone');
            }
        });

        // Payments table indexes
        Schema::table('payments', function (Blueprint $table) {
            // Index for booking payments
            $table->index(['booking_id', 'status'], 'idx_payments_booking_status');

            // Index for user payment history
            $table->index(['user_id', 'status', 'created_at'], 'idx_payments_user_status_created');

            // Index for gateway queries
            $table->index(['gateway', 'payment_id'], 'idx_payments_gateway_id');

            // Index for method queries
            $table->index(['method', 'status'], 'idx_payments_method_status');
        });

        // Wallet transactions table indexes
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Index for user transaction history
            $table->index(['user_id', 'created_at'], 'idx_wallet_transactions_user_created');

            // Index for transaction type queries
            $table->index(['user_id', 'type', 'created_at'], 'idx_wallet_transactions_user_type_created');

            // Index for reference lookups
            $table->index(['reference_number'], 'idx_wallet_transactions_reference');
        });

        // Reviews table indexes
        Schema::table('reviews', function (Blueprint $table) {
            // Index for provider reviews
            $table->index(['provider_id', 'is_approved'], 'idx_reviews_provider_approved');

            // Index for user reviews
            $table->index(['user_id', 'created_at'], 'idx_reviews_user_created');

            // Index for booking reviews
            $table->index(['booking_id'], 'idx_reviews_booking');

            // Index for flagged reviews
            $table->index(['is_flagged', 'is_approved'], 'idx_reviews_flagged_approved');
        });

        // Notifications table indexes
        Schema::table('notifications', function (Blueprint $table) {
            // Index for user notifications
            $table->index(['user_id', 'is_read', 'created_at'], 'idx_notifications_user_read_created');

            // Index for notification type
            $table->index(['type', 'created_at'], 'idx_notifications_type_created');
        });

        // Cities table indexes
        Schema::table('cities', function (Blueprint $table) {
            // Index for active cities
            $table->index(['is_active', 'name_en'], 'idx_cities_active_name');
        });

        // Service categories table indexes
        Schema::table('service_categories', function (Blueprint $table) {
            // Index for active categories
            $table->index(['is_active', 'sort_order'], 'idx_categories_active_sort');
        });

        // Promo codes table indexes
        Schema::table('promo_codes', function (Blueprint $table) {
            // Index for code lookup
            $table->index(['code', 'is_active'], 'idx_promo_codes_code_active');

            // Index for date-based queries
            $table->index(['is_active', 'start_date', 'end_date'], 'idx_promo_codes_active_dates');
        });

        // Withdrawal requests table indexes
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            // Index for provider withdrawals
            $table->index(['provider_id', 'status', 'created_at'], 'idx_withdrawals_provider_status_created');

            // Index for admin management
            $table->index(['status', 'created_at'], 'idx_withdrawals_status_created');
        });

        // Device tokens table indexes (if exists)
        if (Schema::hasTable('device_tokens')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                // Index for user tokens
                $table->index(['user_id', 'is_active'], 'idx_device_tokens_user_active');

                // Index for token lookup
                $table->index(['token'], 'idx_device_tokens_token');
            });
        }

        // Jobs table indexes (for queue performance)
        Schema::table('jobs', function (Blueprint $table) {
            // Index for queue processing
            $table->index(['queue', 'reserved_at'], 'idx_jobs_queue_reserved');
        });
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Drop bookings indexes
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('idx_bookings_provider_status_date');
            $table->dropIndex('idx_bookings_client_status_created');
            $table->dropIndex('idx_bookings_payment_status');
            $table->dropIndex('idx_bookings_payment_deadline');
            $table->dropIndex('idx_bookings_status_created');
        });

        // Drop services indexes
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('idx_services_provider_active');
            $table->dropIndex('idx_services_category_active_price');
            $table->dropIndex('idx_services_home_active');
            $table->dropIndex('idx_services_active_price');
        });

        // Drop service_providers indexes
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropIndex('idx_providers_status_active');
            $table->dropIndex('idx_providers_city_active_verified');
            $table->dropIndex('idx_providers_featured_rating');
            $table->dropIndex('idx_providers_type_active');
            $table->dropIndex('idx_providers_location');
        });

        // Drop booking_items indexes
        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropIndex('idx_booking_items_booking_service');
            $table->dropIndex('idx_booking_items_service');
        });

        // Drop users indexes
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_type_status');
            $table->dropIndex('idx_users_city_type');
            if (Schema::hasColumn('users', 'idx_users_phone')) {
                $table->dropIndex('idx_users_phone');
            }
        });

        // Drop payments indexes
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_booking_status');
            $table->dropIndex('idx_payments_user_status_created');
            $table->dropIndex('idx_payments_gateway_id');
            $table->dropIndex('idx_payments_method_status');
        });

        // Drop wallet_transactions indexes
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_wallet_transactions_user_created');
            $table->dropIndex('idx_wallet_transactions_user_type_created');
            $table->dropIndex('idx_wallet_transactions_reference');
        });

        // Drop reviews indexes
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('idx_reviews_provider_approved');
            $table->dropIndex('idx_reviews_user_created');
            $table->dropIndex('idx_reviews_booking');
            $table->dropIndex('idx_reviews_flagged_approved');
        });

        // Drop notifications indexes
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_read_created');
            $table->dropIndex('idx_notifications_type_created');
        });

        // Drop cities indexes
        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex('idx_cities_active_name');
        });

        // Drop service_categories indexes
        Schema::table('service_categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_active_sort');
        });

        // Drop promo_codes indexes
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropIndex('idx_promo_codes_code_active');
            $table->dropIndex('idx_promo_codes_active_dates');
        });

        // Drop withdrawal_requests indexes
        Schema::table('withdrawal_requests', function (Blueprint $table) {
            $table->dropIndex('idx_withdrawals_provider_status_created');
            $table->dropIndex('idx_withdrawals_status_created');
        });

        // Drop device_tokens indexes (if exists)
        if (Schema::hasTable('device_tokens')) {
            Schema::table('device_tokens', function (Blueprint $table) {
                $table->dropIndex('idx_device_tokens_user_active');
                $table->dropIndex('idx_device_tokens_token');
            });
        }

        // Drop jobs indexes
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('idx_jobs_queue_reserved');
        });
    }
};
