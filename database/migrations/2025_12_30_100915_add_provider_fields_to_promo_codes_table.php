<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            // Add provider_id for provider-owned promo codes (nullable for admin codes)
            $table->foreignId('provider_id')->nullable()->after('created_by')
                ->constrained('users')->onDelete('cascade');

            // Add free_service_id for free service discount type
            $table->foreignId('free_service_id')->nullable()->after('discount_value')
                ->constrained('services')->onDelete('set null');

            // Add index for provider_id for faster queries
            $table->index('provider_id');
        });

        // Update discount_type enum to include 'free_service'
        DB::statement("ALTER TABLE promo_codes DROP CONSTRAINT IF EXISTS promo_codes_discount_type_check");
        DB::statement("ALTER TABLE promo_codes ADD CONSTRAINT promo_codes_discount_type_check CHECK (discount_type IN ('percentage', 'fixed', 'free_service'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
            $table->dropForeign(['free_service_id']);
            $table->dropIndex(['provider_id']);
            $table->dropColumn(['provider_id', 'free_service_id']);
        });

        // Revert discount_type enum to original values
        DB::statement("ALTER TABLE promo_codes DROP CONSTRAINT IF EXISTS promo_codes_discount_type_check");
        DB::statement("ALTER TABLE promo_codes ADD CONSTRAINT promo_codes_discount_type_check CHECK (discount_type IN ('percentage', 'fixed'))");
    }
};
