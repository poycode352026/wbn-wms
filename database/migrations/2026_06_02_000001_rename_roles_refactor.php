<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Expand users.role enum to include both old + new values ─────────
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'super_admin','procurement_admin','wh_admin','admin_dept','manager_dept',
            'wh_manager','wh_supervisor','admin_pr','warehouse_manager','supervisor',
            'operator','employee','user'
        ) NOT NULL DEFAULT 'user'");

        // ── 2. Migrate existing user data ──────────────────────────────────────
        DB::table('users')->where('role', 'admin_pr')->update(['role' => 'procurement_admin']);
        DB::table('users')->where('role', 'warehouse_manager')->update(['role' => 'wh_manager']);
        DB::table('users')->where('role', 'supervisor')->update(['role' => 'wh_supervisor']);

        // ── 3. Remove old values from enum ────────────────────────────────────
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'super_admin','procurement_admin','wh_admin','admin_dept','manager_dept',
            'wh_manager','wh_supervisor','operator','employee','user'
        ) NOT NULL DEFAULT 'user'");

        // ── 4. Update role_permissions table ──────────────────────────────────
        DB::table('role_permissions')->where('role', 'admin_pr')->update(['role' => 'procurement_admin']);
        DB::table('role_permissions')->where('role', 'warehouse_manager')->update(['role' => 'wh_manager']);
        DB::table('role_permissions')->where('role', 'supervisor')->update(['role' => 'wh_supervisor']);

        // ── 5. goods_issue_approvals.step enum ────────────────────────────────
        DB::statement("ALTER TABLE goods_issue_approvals MODIFY step ENUM(
            'manager_dept','wh_manager','wh_supervisor','warehouse_manager','supervisor','operator'
        ) NOT NULL");
        DB::table('goods_issue_approvals')->where('step', 'warehouse_manager')->update(['step' => 'wh_manager']);
        DB::table('goods_issue_approvals')->where('step', 'supervisor')->update(['step' => 'wh_supervisor']);
        DB::statement("ALTER TABLE goods_issue_approvals MODIFY step ENUM(
            'manager_dept','wh_manager','wh_supervisor','operator'
        ) NOT NULL");

        // ── 6. goods_receipt_approvals.step enum ──────────────────────────────
        DB::statement("ALTER TABLE goods_receipt_approvals MODIFY step ENUM('wh_supervisor','supervisor') NOT NULL");
        DB::table('goods_receipt_approvals')->where('step', 'supervisor')->update(['step' => 'wh_supervisor']);
        DB::statement("ALTER TABLE goods_receipt_approvals MODIFY step ENUM('wh_supervisor') NOT NULL");
    }

    public function down(): void
    {
        // ── Reverse goods_receipt_approvals.step ──────────────────────────────
        DB::statement("ALTER TABLE goods_receipt_approvals MODIFY step ENUM('wh_supervisor','supervisor') NOT NULL");
        DB::table('goods_receipt_approvals')->where('step', 'wh_supervisor')->update(['step' => 'supervisor']);
        DB::statement("ALTER TABLE goods_receipt_approvals MODIFY step ENUM('supervisor') NOT NULL");

        // ── Reverse goods_issue_approvals.step ────────────────────────────────
        DB::statement("ALTER TABLE goods_issue_approvals MODIFY step ENUM(
            'manager_dept','wh_manager','wh_supervisor','warehouse_manager','supervisor','operator'
        ) NOT NULL");
        DB::table('goods_issue_approvals')->where('step', 'wh_manager')->update(['step' => 'warehouse_manager']);
        DB::table('goods_issue_approvals')->where('step', 'wh_supervisor')->update(['step' => 'supervisor']);
        DB::statement("ALTER TABLE goods_issue_approvals MODIFY step ENUM(
            'manager_dept','warehouse_manager','supervisor','operator'
        ) NOT NULL");

        // ── Reverse role_permissions ───────────────────────────────────────────
        DB::table('role_permissions')->where('role', 'procurement_admin')->update(['role' => 'admin_pr']);
        DB::table('role_permissions')->where('role', 'wh_manager')->update(['role' => 'warehouse_manager']);
        DB::table('role_permissions')->where('role', 'wh_supervisor')->update(['role' => 'supervisor']);

        // ── Reverse users.role enum ────────────────────────────────────────────
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'super_admin','procurement_admin','wh_admin','admin_dept','manager_dept',
            'wh_manager','wh_supervisor','admin_pr','warehouse_manager','supervisor',
            'operator','employee','user'
        ) NOT NULL DEFAULT 'user'");
        DB::table('users')->where('role', 'procurement_admin')->update(['role' => 'admin_pr']);
        DB::table('users')->where('role', 'wh_manager')->update(['role' => 'warehouse_manager']);
        DB::table('users')->where('role', 'wh_supervisor')->update(['role' => 'supervisor']);
        DB::statement("ALTER TABLE users MODIFY role ENUM(
            'super_admin','admin_pr','wh_admin','admin_dept','manager_dept',
            'warehouse_manager','supervisor','operator','employee','user'
        ) NOT NULL DEFAULT 'user'");
    }
};
