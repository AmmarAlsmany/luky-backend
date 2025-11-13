<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
            $table->string('name_ar')->nullable()->after('name_en');
        });

        // Update existing roles with English names
        DB::table('roles')->update([
            'name_en' => DB::raw('name'),
        ]);

        // Add Arabic translations for common roles
        $roleTranslations = [
            'admin' => 'مدير',
            'provider' => 'مقدم خدمة',
            'client' => 'عميل',
            'super_admin' => 'مدير عام',
        ];

        foreach ($roleTranslations as $name => $nameAr) {
            DB::table('roles')
                ->where('name', $name)
                ->update(['name_ar' => $nameAr]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_ar']);
        });
    }
};
