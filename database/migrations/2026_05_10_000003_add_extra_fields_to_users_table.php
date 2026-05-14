<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('is_active')->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('last_login_at');
        });
    }
};
