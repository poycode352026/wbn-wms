<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('expected_qty', 10, 2);
            $table->decimal('actual_qty', 10, 2)->nullable();
            $table->string('uom', 50);
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('condition', ['good', 'damaged', 'broken', 'other'])->default('good');
            $table->text('condition_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};