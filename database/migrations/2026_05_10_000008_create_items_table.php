<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('item_categories')->cascadeOnDelete();
            $table->string('part_number', 100)->unique();
            $table->string('name_id', 255);
            $table->string('name_en', 255);
            $table->string('name_zh', 255);
            $table->text('description')->nullable();
            $table->string('base_uom', 50);
            $table->string('alt_uom', 50)->nullable();
            $table->decimal('alt_uom_conversion', 10, 2)->nullable();
            $table->decimal('minimum_stock', 10, 2)->default(0);
            $table->boolean('has_cooldown')->default(false);
            $table->integer('cooldown_days')->nullable();
            $table->enum('cooldown_track_by', ['lv_number', 'employee_id'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};