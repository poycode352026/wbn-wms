<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_request_items', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('item_variant_id')->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('warehouse_id')->constrained()->nullOnDelete();
        });

        // Make goods_requests.warehouse_id nullable (GRQ can span multiple warehouses)
        Schema::table('goods_requests', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('goods_request_items', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['location_id']);
            $table->dropColumn(['warehouse_id', 'location_id']);
        });

        Schema::table('goods_requests', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable(false)->change();
        });
    }
};
