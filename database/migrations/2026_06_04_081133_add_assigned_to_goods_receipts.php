<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete()->after('approved_by');
            $table->timestamp('assigned_at')->nullable()->after('inspected_at');
        });

        // Extend status enum to include 'assigned' (MySQL only — SQLite stores enum as TEXT)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE goods_receipts MODIFY COLUMN status ENUM('draft','arrived','assigned','pending_supervisor','completed') DEFAULT 'draft'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE goods_receipts MODIFY COLUMN status ENUM('draft','arrived','pending_supervisor','completed') DEFAULT 'draft'");
        }

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn(['assigned_to', 'assigned_at']);
        });
    }
};
