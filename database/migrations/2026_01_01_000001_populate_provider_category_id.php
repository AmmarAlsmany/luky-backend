<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\ServiceProvider;
use App\Models\ProviderCategory;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Map old business_type to new provider categories
        $businessTypeMapping = [
            'salon' => 'Women\'s Beauty Salon',
            'barbershop' => 'Men\'s Barber Shop',
            'beauty_center' => 'Beauty Center',
            'spa' => 'Spa & Wellness',
            'clinic' => 'Beauty Clinic',
            'makeup_artist' => 'Makeup Artist',
            'hair_stylist' => 'Hair Stylist',
            'nail_salon' => 'Nail Salon',
            'massage' => 'Massage Center',
        ];

        // Get all provider categories
        $categories = ProviderCategory::all()->keyBy('name_en');

        // Update all providers
        foreach ($businessTypeMapping as $businessType => $categoryName) {
            if (isset($categories[$categoryName])) {
                $categoryId = $categories[$categoryName]->id;

                ServiceProvider::where('business_type', $businessType)
                    ->whereNull('provider_category_id')
                    ->update(['provider_category_id' => $categoryId]);

                echo "Updated providers with business_type='$businessType' to category '$categoryName' (ID: $categoryId)\n";
            }
        }

        // Handle any providers without business_type or with unknown types
        // Set them to "Beauty Center" as default
        $defaultCategory = $categories['Beauty Center'] ?? null;
        if ($defaultCategory) {
            ServiceProvider::whereNull('provider_category_id')
                ->update(['provider_category_id' => $defaultCategory->id]);

            echo "Set default category for remaining providers\n";
        }

        echo "Migration completed successfully!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set all provider_category_id to null (rollback)
        ServiceProvider::whereNotNull('provider_category_id')
            ->update(['provider_category_id' => null]);
    }
};
