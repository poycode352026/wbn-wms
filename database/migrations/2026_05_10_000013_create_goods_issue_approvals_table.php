<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_issue_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_issue_id')->constrained()->cascadeOnDelete();
            $table->enum('step', ['manager_dept', 'warehouse_manager', 'supervisor', 'operator']);
            $table->enum('action', ['approved', 'rejected']);
            $table->foreignId('acted_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamp('acted_at');
            $table->timestamps();
            $table->index('goods_issue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_issue_approvals');
    }
};