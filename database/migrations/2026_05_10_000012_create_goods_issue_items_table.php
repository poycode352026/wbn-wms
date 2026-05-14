<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_qty', 10, 2);
            $table->string('requested_uom', 50);
            $table->decimal('qty_in_base_uom', 10, 2);
            $table->decimal('actual_qty', 10, 2)->nullable();
            $table->string('uom_used', 50);
            $table->foreignId('lv_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('cooldown_until')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'picking', 'ready', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_issue_items');
    }
};