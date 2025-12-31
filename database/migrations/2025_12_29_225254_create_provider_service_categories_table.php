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
        Schema::create('provider_service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('service_providers')->onDelete('cascade');
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('color')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for better performance
            $table->index('provider_id');
            $table->index(['provider_id', 'is_active', 'sort_order']);
        });

        // Add provider_service_category_id to services table
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('provider_service_category_id')
                ->nullable()
                ->after('category_id')
                ->constrained('provider_service_categories')
                ->onDelete('set null');

            $table->index('provider_service_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the foreign key from services table first
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['provider_service_category_id']);
            $table->dropColumn('provider_service_category_id');
        });

        Schema::dropIfExists('provider_service_categories');
    }
};
