<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_qty', 10, 2);
            $table->string('uom_used', 50);
            $table->decimal('qty_in_base_uom', 10, 2);
            $table->timestamps();

            $table->index('goods_request_id');
            $table->index('item_variant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_request_items');
    }
};
