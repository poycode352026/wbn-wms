<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty_on_hand', 10, 2)->default(0);
            $table->decimal('qty_reserved', 10, 2)->default(0);
            $table->decimal('qty_available', 10, 2)->storedAs('qty_on_hand - qty_reserved');
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            $table->unique(['item_variant_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};