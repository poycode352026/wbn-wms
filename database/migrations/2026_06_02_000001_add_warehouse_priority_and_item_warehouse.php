<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Warehouse priority (lower = closer / higher priority)
        Schema::table('warehouses', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(99)->after('is_active');
        });

        // 2. Per-item warehouse override on GI items
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->foreignId('item_warehouse_id')
                ->nullable()
                ->constrained('warehouses')
                ->nullOnDelete()
                ->after('goods_issue_id');
        });
    }

    public function down(): void
    {
        Schema::table('goods_issue_items', function (Blueprint $table) {
            $table->dropForeign(['item_warehouse_id']);
            $table->dropColumn('item_warehouse_id');
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
