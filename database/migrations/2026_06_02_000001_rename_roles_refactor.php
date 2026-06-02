<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Expand users.role to include both old + new values ─────────────
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin', 'procurement_admin', 'wh_admin', 'admin_dept', 'manager_dept',
                'wh_manager', 'wh_supervisor', 'admin_pr', 'warehouse_manager', 'supervisor',
                'operator', 'employee', 'user',
            ])->default('user')->change();
        });

        // ── 2. Migrate existing user data ──────────────────────────────────────
        DB::table('users')->where('role', 'admin_pr')->update(['role' => 'procurement_admin']);
        DB::table('users')->where('role', 'warehouse_manager')->update(['role' => 'wh_manager']);
        DB::table('users')->where('role', 'supervisor')->update(['role' => 'wh_supervisor']);

        // ── 3. Narrow users.role to final values only ─────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin', 'procurement_admin', 'wh_admin', 'admin_dept', 'manager_dept',
                'wh_manager', 'wh_supervisor', 'operator', 'employee', 'user',
            ])->default('user')->change();
        });

        // ── 4. Update role_permissions table (only if it exists) ──────────────
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('role', 'admin_pr')->update(['role' => 'procurement_admin']);
            DB::table('role_permissions')->where('role', 'warehouse_manager')->update(['role' => 'wh_manager']);
            DB::table('role_permissions')->where('role', 'supervisor')->update(['role' => 'wh_supervisor']);
        }

        // ── 5. goods_issue_approvals.step ─────────────────────────────────────
        Schema::table('goods_issue_approvals', function (Blueprint $table) {
            $table->enum('step', [
                'manager_dept', 'wh_manager', 'wh_supervisor', 'warehouse_manager', 'supervisor', 'operator',
            ])->change();
        });
        DB::table('goods_issue_approvals')->where('step', 'warehouse_manager')->update(['step' => 'wh_manager']);
        DB::table('goods_issue_approvals')->where('step', 'supervisor')->update(['step' => 'wh_supervisor']);
        Schema::table('goods_issue_approvals', function (Blueprint $table) {
            $table->enum('step', [
                'manager_dept', 'wh_manager', 'wh_supervisor', 'operator',
            ])->change();
        });

        // ── 6. goods_receipt_approvals.step ───────────────────────────────────
        Schema::table('goods_receipt_approvals', function (Blueprint $table) {
            $table->enum('step', ['wh_supervisor', 'supervisor'])->change();
        });
        DB::table('goods_receipt_approvals')->where('step', 'supervisor')->update(['step' => 'wh_supervisor']);
        Schema::table('goods_receipt_approvals', function (Blueprint $table) {
            $table->enum('step', ['wh_supervisor'])->change();
        });
    }

    public function down(): void
    {
        // ── Reverse goods_receipt_approvals.step ──────────────────────────────
        Schema::table('goods_receipt_approvals', function (Blueprint $table) {
            $table->enum('step', ['wh_supervisor', 'supervisor'])->change();
        });
        DB::table('goods_receipt_approvals')->where('step', 'wh_supervisor')->update(['step' => 'supervisor']);
        Schema::table('goods_receipt_approvals', function (Blueprint $table) {
            $table->enum('step', ['supervisor'])->change();
        });

        // ── Reverse goods_issue_approvals.step ────────────────────────────────
        Schema::table('goods_issue_approvals', function (Blueprint $table) {
            $table->enum('step', [
                'manager_dept', 'wh_manager', 'wh_supervisor', 'warehouse_manager', 'supervisor', 'operator',
            ])->change();
        });
        DB::table('goods_issue_approvals')->where('step', 'wh_manager')->update(['step' => 'warehouse_manager']);
        DB::table('goods_issue_approvals')->where('step', 'wh_supervisor')->update(['step' => 'supervisor']);
        Schema::table('goods_issue_approvals', function (Blueprint $table) {
            $table->enum('step', [
                'manager_dept', 'warehouse_manager', 'supervisor', 'operator',
            ])->change();
        });

        // ── Reverse role_permissions ───────────────────────────────────────────
        if (Schema::hasTable('role_permissions')) {
            DB::table('role_permissions')->where('role', 'procurement_admin')->update(['role' => 'admin_pr']);
            DB::table('role_permissions')->where('role', 'wh_manager')->update(['role' => 'warehouse_manager']);
            DB::table('role_permissions')->where('role', 'wh_supervisor')->update(['role' => 'supervisor']);
        }

        // ── Reverse users.role ────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin', 'procurement_admin', 'wh_admin', 'admin_dept', 'manager_dept',
                'wh_manager', 'wh_supervisor', 'admin_pr', 'warehouse_manager', 'supervisor',
                'operator', 'employee', 'user',
            ])->default('user')->change();
        });
        DB::table('users')->where('role', 'procurement_admin')->update(['role' => 'admin_pr']);
        DB::table('users')->where('role', 'wh_manager')->update(['role' => 'warehouse_manager']);
        DB::table('users')->where('role', 'wh_supervisor')->update(['role' => 'supervisor']);
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'super_admin', 'admin_pr', 'wh_admin', 'admin_dept', 'manager_dept',
                'warehouse_manager', 'supervisor', 'operator', 'employee', 'user',
            ])->default('user')->change();
        });
    }
};
