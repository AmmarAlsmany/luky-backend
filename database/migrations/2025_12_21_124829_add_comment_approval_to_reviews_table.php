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
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('comment_approved')->default(false)->after('comment');
            $table->timestamp('comment_approved_at')->nullable()->after('comment_approved');
            $table->foreignId('comment_approved_by')->nullable()->constrained('users')->onDelete('set null')->after('comment_approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['comment_approved_by']);
            $table->dropColumn(['comment_approved', 'comment_approved_at', 'comment_approved_by']);
        });
    }
};
