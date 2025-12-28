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
        Schema::table('service_providers', function (Blueprint $table) {
            $table->foreignId('provider_category_id')->nullable()->after('business_type')->constrained('provider_categories')->nullOnDelete();
            $table->index('provider_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropForeign(['provider_category_id']);
            $table->dropIndex(['provider_category_id']);
            $table->dropColumn('provider_category_id');
        });
    }
};
