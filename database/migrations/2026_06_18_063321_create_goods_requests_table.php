<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_requests', function (Blueprint $table) {
            $table->id();
            $table->string('grq_number', 50)->unique();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('requester_name', 255);
            $table->string('requester_emp_id', 100)->nullable();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->text('remark')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->enum('status', ['completed', 'cancelled'])->default('completed');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('warehouse_id');
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_requests');
    }
};
