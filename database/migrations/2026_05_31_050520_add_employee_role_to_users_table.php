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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin',
                'admin_pr',
                'wh_admin',
                'admin_dept',
                'manager_dept',
                'warehouse_manager',
                'supervisor',
                'operator',
                'employee',
                'user',
            ])->default('user')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin',
                'admin_pr',
                'wh_admin',
                'admin_dept',
                'manager_dept',
                'warehouse_manager',
                'supervisor',
                'operator',
                'user',
            ])->default('user')->change();
        });
    }
};
