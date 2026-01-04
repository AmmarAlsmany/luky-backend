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
        // Step 1: Drop the existing foreign key constraint to users
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
        });

        // Step 2: Update existing data - convert user_id to service_provider_id
        DB::statement('
            UPDATE promo_codes
            SET provider_id = service_providers.id
            FROM service_providers
            WHERE promo_codes.provider_id = service_providers.user_id
            AND promo_codes.provider_id IS NOT NULL
        ');

        // Step 3: Add the correct foreign key constraint to service_providers
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->foreign('provider_id')
                ->references('id')
                ->on('service_providers')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Drop the foreign key to service_providers
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropForeign(['provider_id']);
        });

        // Step 2: Revert data - convert service_provider_id back to user_id
        DB::statement('
            UPDATE promo_codes
            SET provider_id = service_providers.user_id
            FROM service_providers
            WHERE promo_codes.provider_id = service_providers.id
            AND promo_codes.provider_id IS NOT NULL
        ');

        // Step 3: Restore the original foreign key to users
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->foreign('provider_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
