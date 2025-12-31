<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class InitialProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Create Permissions (English & Arabic)
        $this->createPermissions();

        // 2. Create Roles (English & Arabic)
        $this->createRoles();

        // 3. Assign Permissions to Roles
        $this->assignPermissionsToRoles();

        // 4. Create Cities (English & Arabic)
        $this->createCities();

        // 5. Create Provider Categories
        $this->call(ProviderCategorySeeder::class);

        // 6. Create Static Pages
        $this->call(StaticPagesSeeder::class);

        // 7. Create Super Admin User
        $this->createSuperAdmin();

        $this->command->info('✅ Initial production data seeded successfully!');
    }

    private function createPermissions()
    {
        $permissions = [
            // Dashboard
            ['name' => 'view_dashboard', 'guard_name' => 'web'],
            
            // Clients Management
            ['name' => 'view_clients', 'guard_name' => 'web'],
            ['name' => 'manage_clients', 'guard_name' => 'web'],
            ['name' => 'create_clients', 'guard_name' => 'web'],
            ['name' => 'edit_clients', 'guard_name' => 'web'],
            ['name' => 'delete_clients', 'guard_name' => 'web'],
            
            // Providers Management
            ['name' => 'view_providers', 'guard_name' => 'web'],
            ['name' => 'manage_providers', 'guard_name' => 'web'],
            ['name' => 'approve_providers', 'guard_name' => 'web'],
            ['name' => 'create_providers', 'guard_name' => 'web'],
            ['name' => 'edit_providers', 'guard_name' => 'web'],
            ['name' => 'delete_providers', 'guard_name' => 'web'],
            
            // Bookings Management
            ['name' => 'view_bookings', 'guard_name' => 'web'],
            ['name' => 'manage_bookings', 'guard_name' => 'web'],
            ['name' => 'create_bookings', 'guard_name' => 'web'],
            ['name' => 'edit_bookings', 'guard_name' => 'web'],
            ['name' => 'cancel_bookings', 'guard_name' => 'web'],
            ['name' => 'export_bookings', 'guard_name' => 'web'],
            
            // Services Management
            ['name' => 'view_services', 'guard_name' => 'web'],
            ['name' => 'manage_services', 'guard_name' => 'web'],
            ['name' => 'create_services', 'guard_name' => 'web'],
            ['name' => 'edit_services', 'guard_name' => 'web'],
            ['name' => 'delete_services', 'guard_name' => 'web'],
            
            // Categories Management
            ['name' => 'manage_categories', 'guard_name' => 'web'],
            ['name' => 'view_categories', 'guard_name' => 'web'],
            ['name' => 'create_categories', 'guard_name' => 'web'],
            ['name' => 'edit_categories', 'guard_name' => 'web'],
            ['name' => 'delete_categories', 'guard_name' => 'web'],
            
            // Promo Codes Management
            ['name' => 'view_promo_codes', 'guard_name' => 'web'],
            ['name' => 'create_promo_codes', 'guard_name' => 'web'],
            ['name' => 'edit_promo_codes', 'guard_name' => 'web'],
            ['name' => 'delete_promo_codes', 'guard_name' => 'web'],
            
            // Employees/Users Management
            ['name' => 'view_users', 'guard_name' => 'web'],
            ['name' => 'manage_users', 'guard_name' => 'web'],
            ['name' => 'view_employees', 'guard_name' => 'web'],
            ['name' => 'create_employees', 'guard_name' => 'web'],
            ['name' => 'edit_employees', 'guard_name' => 'web'],
            ['name' => 'delete_employees', 'guard_name' => 'web'],
            
            // Roles & Permissions
            ['name' => 'view_roles', 'guard_name' => 'web'],
            ['name' => 'manage_roles', 'guard_name' => 'web'],
            ['name' => 'create_roles', 'guard_name' => 'web'],
            ['name' => 'edit_roles', 'guard_name' => 'web'],
            ['name' => 'assign_permissions', 'guard_name' => 'web'],
            
            // Reviews Management
            ['name' => 'view_reviews', 'guard_name' => 'web'],
            ['name' => 'manage_reviews', 'guard_name' => 'web'],
            ['name' => 'hide_reviews', 'guard_name' => 'web'],
            ['name' => 'delete_reviews', 'guard_name' => 'web'],
            
            // Notifications
            ['name' => 'view_notifications', 'guard_name' => 'web'],
            ['name' => 'send_notifications', 'guard_name' => 'web'],
            ['name' => 'manage_notifications', 'guard_name' => 'web'],
            
            // Payments Management
            ['name' => 'view_payments', 'guard_name' => 'web'],
            ['name' => 'manage_payments', 'guard_name' => 'web'],
            ['name' => 'manage_payment_settings', 'guard_name' => 'web'],
            
            // Banners Management
            ['name' => 'manage_banners', 'guard_name' => 'web'],
            
            // Static Pages Management
            ['name' => 'manage_static_pages', 'guard_name' => 'web'],
            
            // Reports
            ['name' => 'view_reports', 'guard_name' => 'web'],
            ['name' => 'export_reports', 'guard_name' => 'web'],
            
            // General Settings
            ['name' => 'manage_general_settings', 'guard_name' => 'web'],
            
            // Support Tickets
            ['name' => 'view_tickets', 'guard_name' => 'web'],
            ['name' => 'create_tickets', 'guard_name' => 'web'],
            ['name' => 'close_tickets', 'guard_name' => 'web'],
            ['name' => 'assign_tickets', 'guard_name' => 'web'],
            
            // Chat/Messages
            ['name' => 'view_chat', 'guard_name' => 'web'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate($permission);
        }

        $this->command->info('✓ ' . count($permissions) . ' Permissions created');
    }

    private function createRoles()
    {
        $roles = [
            [
                'name' => 'super_admin',
                'name_en' => 'Super Admin',
                'name_ar' => 'مدير عام',
                'guard_name' => 'web'
            ],
            [
                'name' => 'admin',
                'name_en' => 'Admin',
                'name_ar' => 'مدير',
                'guard_name' => 'web'
            ],
            [
                'name' => 'provider',
                'name_en' => 'Service Provider',
                'name_ar' => 'مقدم خدمة',
                'guard_name' => 'web'
            ],
            [
                'name' => 'client',
                'name_en' => 'Client',
                'name_ar' => 'عميل',
                'guard_name' => 'web'
            ],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name'], 'guard_name' => $roleData['guard_name']],
                ['name_en' => $roleData['name_en'], 'name_ar' => $roleData['name_ar']]
            );
        }

        $this->command->info('✓ Roles created');
    }

    private function assignPermissionsToRoles()
    {
        // Super Admin - All permissions
        $superAdmin = Role::where('name', 'super_admin')->first();
        $superAdmin->syncPermissions(Permission::all());

        // Admin - Most permissions except role management
        $admin = Role::where('name', 'admin')->first();
        $adminPermissions = Permission::whereNotIn('name', [
            'manage_roles',
            'manage_permissions'
        ])->get();
        $admin->syncPermissions($adminPermissions);

        // Provider - Limited permissions
        $provider = Role::where('name', 'provider')->first();
        $provider->syncPermissions([
            'view_dashboard',
            'view_bookings',
            'edit_bookings',
            'view_services',
            'create_services',
            'edit_services',
            'delete_services',
            'view_reviews',
            'view_tickets',
        ]);

        // Client - Minimal permissions
        $client = Role::where('name', 'client')->first();
        $client->syncPermissions([
            'view_bookings',
            'create_bookings',
        ]);

        $this->command->info('✓ Permissions assigned to roles');
    }

    private function createCities()
    {
        $cities = [
            // Major Cities
            ['name_en' => 'Riyadh', 'name_ar' => 'الرياض'],
            ['name_en' => 'Jeddah', 'name_ar' => 'جدة'],
            ['name_en' => 'Mecca', 'name_ar' => 'مكة المكرمة'],
            ['name_en' => 'Medina', 'name_ar' => 'المدينة المنورة'],
            ['name_en' => 'Dammam', 'name_ar' => 'الدمام'],
            ['name_en' => 'Khobar', 'name_ar' => 'الخبر'],
            ['name_en' => 'Dhahran', 'name_ar' => 'الظهران'],
            ['name_en' => 'Taif', 'name_ar' => 'الطائف'],
            ['name_en' => 'Buraidah', 'name_ar' => 'بريدة'],
            ['name_en' => 'Tabuk', 'name_ar' => 'تبوك'],
            ['name_en' => 'Khamis Mushait', 'name_ar' => 'خميس مشيط'],
            ['name_en' => 'Hail', 'name_ar' => 'حائل'],
            ['name_en' => 'Hofuf', 'name_ar' => 'الهفوف'],
            ['name_en' => 'Jubail', 'name_ar' => 'الجبيل'],
            ['name_en' => 'Hafr Al-Batin', 'name_ar' => 'حفر الباطن'],
            ['name_en' => 'Yanbu', 'name_ar' => 'ينبع'],
            ['name_en' => 'Abha', 'name_ar' => 'أبها'],
            ['name_en' => 'Arar', 'name_ar' => 'عرعر'],
            ['name_en' => 'Sakaka', 'name_ar' => 'سكاكا'],
            ['name_en' => 'Jizan', 'name_ar' => 'جازان'],
            ['name_en' => 'Al-Qatif', 'name_ar' => 'القطيف'],
            ['name_en' => 'Najran', 'name_ar' => 'نجران'],
            ['name_en' => 'Al-Kharj', 'name_ar' => 'الخرج'],
            ['name_en' => 'Al-Ahsa', 'name_ar' => 'الأحساء'],
            ['name_en' => 'Qassim', 'name_ar' => 'القصيم'],
        ];

        foreach ($cities as $city) {
            DB::table('cities')->insertOrIgnore([
                'name_en' => $city['name_en'],
                'name_ar' => $city['name_ar'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('✓ Cities created');
    }

    private function createSuperAdmin()
    {
        // Check if super admin already exists
        $existingAdmin = \App\Models\User::where('email', 'admin@luky.sa')->first();
        
        if ($existingAdmin) {
            // Update existing admin
            $existingAdmin->update([
                'status' => 'active',
                'is_active' => true,
            ]);
            
            // Ensure super_admin role is assigned
            $existingAdmin->syncRoles(['super_admin']);
            
            $this->command->info('✓ Super Admin updated with ' . $existingAdmin->getAllPermissions()->count() . ' permissions');
            return;
        }
        
        // Create Super Admin User
        $superAdmin = \App\Models\User::create([
            'name' => 'Super Admin',
            'email' => 'admin@luky.sa',
            'phone' => '+966500000000',
            'password' => Hash::make('admin123'),
            'user_type' => 'admin',
            'is_active' => true,
            'status' => 'active',
            'phone_verified_at' => now(),
        ]);

        // Assign super_admin role using Spatie's method
        $superAdmin->assignRole('super_admin');

        $this->command->info('✓ Super Admin created with ' . $superAdmin->getAllPermissions()->count() . ' permissions');
        $this->command->warn('⚠️  Super Admin Credentials:');
        $this->command->warn('   Email: admin@luky.sa');
        $this->command->warn('   Phone: +966500000000');
        $this->command->warn('   Password: admin123');
        $this->command->error('   ⚠️  CHANGE THIS PASSWORD IMMEDIATELY IN PRODUCTION!');
    }
}
