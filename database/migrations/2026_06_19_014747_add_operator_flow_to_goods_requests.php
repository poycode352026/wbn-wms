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
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_to');
            $table->foreignId('picked_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable()->after('picked_by');
            $table->timestamp('completed_at')->nullable()->after('picked_at');
            // Widen status column to support new values (pending, assigned, in_picking, ready_to_pickup, completed, cancelled)
            $table->string('status', 30)->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('picked_by');
            $table->dropColumn(['assigned_at', 'picked_at', 'completed_at']);
            $table->string('status', 20)->default('completed')->change();
        });
    }
};
