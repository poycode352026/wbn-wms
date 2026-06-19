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
        Schema::table('goods_request_items', function (Blueprint $table) {
            $table->decimal('actual_qty', 10, 2)->nullable()->after('qty_in_base_uom');
            $table->string('item_status', 20)->nullable()->after('actual_qty'); // ready | rejected
            $table->text('notes')->nullable()->after('item_status');
        });
    }

    public function down(): void
    {
        Schema::table('goods_request_items', function (Blueprint $table) {
            $table->dropColumn(['actual_qty', 'item_status', 'notes']);
        });
    }
};
