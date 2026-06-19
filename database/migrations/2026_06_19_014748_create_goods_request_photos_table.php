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
        Schema::create('goods_request_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_request_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('stage', 20)->default('pickup');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_request_photos');
    }
};
