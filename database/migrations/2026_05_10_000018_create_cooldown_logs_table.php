<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooldown_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_variant_id')->constrained()->cascadeOnDelete();
            $table->enum('track_type', ['lv_number', 'employee_id']);
            $table->foreignId('lv_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('goods_issue_id')->constrained()->cascadeOnDelete();
            $table->date('taken_at');
            $table->date('cooldown_until');
            $table->timestamps();
            $table->index(['item_id', 'track_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooldown_logs');
    }
};