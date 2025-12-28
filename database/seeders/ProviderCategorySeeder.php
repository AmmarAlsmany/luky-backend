<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProviderCategory;

class ProviderCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name_ar' => 'صالون تجميل نسائي',
                'name_en' => 'Women\'s Beauty Salon',
                'description_ar' => 'صالونات تجميل متخصصة في خدمات السيدات',
                'description_en' => 'Beauty salons specialized in women\'s services',
                'icon' => 'salon-women',
                'color' => '#FF69B4',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name_ar' => 'صالون حلاقة رجالي',
                'name_en' => 'Men\'s Barber Shop',
                'description_ar' => 'صالونات حلاقة متخصصة في خدمات الرجال',
                'description_en' => 'Barber shops specialized in men\'s grooming',
                'icon' => 'barbershop',
                'color' => '#1E90FF',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name_ar' => 'مركز تجميل',
                'name_en' => 'Beauty Center',
                'description_ar' => 'مراكز تجميل شاملة توفر خدمات متنوعة',
                'description_en' => 'Comprehensive beauty centers offering various services',
                'icon' => 'beauty-center',
                'color' => '#9370DB',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name_ar' => 'سبا ومنتجع صحي',
                'name_en' => 'Spa & Wellness',
                'description_ar' => 'منتجعات صحية ومراكز سبا للاسترخاء والعناية',
                'description_en' => 'Spa and wellness centers for relaxation and care',
                'icon' => 'spa',
                'color' => '#20B2AA',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'name_ar' => 'عيادة تجميل',
                'name_en' => 'Beauty Clinic',
                'description_ar' => 'عيادات تجميل طبية متخصصة',
                'description_en' => 'Specialized medical beauty clinics',
                'icon' => 'clinic',
                'color' => '#FF6347',
                'sort_order' => 5,
                'is_active' => true,
            ],
            [
                'name_ar' => 'خبيرة مكياج',
                'name_en' => 'Makeup Artist',
                'description_ar' => 'خبراء مكياج محترفون',
                'description_en' => 'Professional makeup artists',
                'icon' => 'makeup',
                'color' => '#FFB6C1',
                'sort_order' => 6,
                'is_active' => true,
            ],
            [
                'name_ar' => 'مصفف شعر',
                'name_en' => 'Hair Stylist',
                'description_ar' => 'مصففو شعر محترفون',
                'description_en' => 'Professional hair stylists',
                'icon' => 'hair-stylist',
                'color' => '#DAA520',
                'sort_order' => 7,
                'is_active' => true,
            ],
            [
                'name_ar' => 'مركز عناية بالأظافر',
                'name_en' => 'Nail Salon',
                'description_ar' => 'صالونات متخصصة في العناية بالأظافر',
                'description_en' => 'Salons specialized in nail care',
                'icon' => 'nail-salon',
                'color' => '#FF1493',
                'sort_order' => 8,
                'is_active' => true,
            ],
            [
                'name_ar' => 'مركز تدليك',
                'name_en' => 'Massage Center',
                'description_ar' => 'مراكز متخصصة في التدليك والاسترخاء',
                'description_en' => 'Centers specialized in massage and relaxation',
                'icon' => 'massage',
                'color' => '#8FBC8F',
                'sort_order' => 9,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            ProviderCategory::create($category);
        }
    }
}
