<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('gr_number', 50)->unique();            // auto: GR-2026-0001
            $table->string('pr_number', 100)->nullable();         // dari admin_pr
            $table->string('po_number', 100)->nullable();         // dari admin_pr
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');              // admin_pr
            $table->foreignId('inspected_by')->nullable()->constrained('users'); // wh_admin
            $table->foreignId('approved_by')->nullable()->constrained('users');  // supervisor / null jika auto
            $table->text('notes')->nullable();
            $table->boolean('auto_approved')->default(false);     // true jika auto-approve 24 jam
            $table->enum('status', [
                'draft', 'arrived', 'pending_supervisor', 'completed',
            ])->default('draft');
            $table->timestamp('submitted_at')->nullable();   // saat admin_pr submit arrived
            $table->timestamp('inspected_at')->nullable();   // saat wh_admin submit inspeksi
            $table->timestamp('completed_at')->nullable();   // saat approved (manual/auto)
            $table->timestamps();
            $table->index('status');
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
