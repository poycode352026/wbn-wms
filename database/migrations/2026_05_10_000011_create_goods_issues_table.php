<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_issues', function (Blueprint $table) {
            $table->id();
            $table->string('gi_number', 50)->unique();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('project', 255)->nullable();          // nama project
            $table->text('purpose');                              // alasan penggunaan / business reason
            $table->string('usage_location', 255);               // area pemakaian
            $table->text('notes')->nullable();
            $table->enum('status', [
                'draft',
                'pending_manager_dept',
                'pending_supervisor',    // WH Supervisor (approval order: supervisor BEFORE warehouse_manager)
                'pending_manager_wh',
                'approved',             // semua approve, menunggu assign operator
                'assigned',
                'in_picking',
                'ready_to_pickup',
                'completed',
                'rejected',
            ])->default('draft');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('picked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('department_id');
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_issues');
    }
};
