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
            $table->boolean('acknowledgment_accepted')->default(false)->after('is_active');
            $table->boolean('undertaking_accepted')->default(false)->after('acknowledgment_accepted');
            $table->timestamp('agreements_accepted_at')->nullable()->after('undertaking_accepted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_providers', function (Blueprint $table) {
            $table->dropColumn(['acknowledgment_accepted', 'undertaking_accepted', 'agreements_accepted_at']);
        });
    }
};
