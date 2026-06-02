<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_request_id')->constrained('employee_requests')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');
            $table->unsignedInteger('qty')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_request_items');
    }
};
