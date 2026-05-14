<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->enum('step', ['supervisor']);
            $table->enum('action', ['approved', 'rejected']);
            $table->foreignId('acted_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
            $table->index('goods_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_approvals');
    }
};