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
            $table->string('account_title')->nullable()->after('address');
            $table->string('account_number')->nullable()->after('account_title');
            $table->string('iban')->nullable()->after('account_number');
            $table->string('currency')->default('SAR')->after('iban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['account_title', 'account_number', 'iban', 'currency']);
        });
    }
};
