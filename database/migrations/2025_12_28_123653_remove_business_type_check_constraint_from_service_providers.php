<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the check constraint on business_type since we now use provider_category_id
        DB::statement('ALTER TABLE service_providers DROP CONSTRAINT IF EXISTS service_providers_business_type_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the constraint with old values (for rollback compatibility)
        DB::statement("ALTER TABLE service_providers ADD CONSTRAINT service_providers_business_type_check CHECK (business_type IN ('salon', 'clinic', 'makeup_artist', 'hair_stylist'))");
    }
};
