<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_issue_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_issue_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name', 255)->nullable();
            $table->enum('stage', ['request', 'picking'])->default('request');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->index('goods_issue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_issue_photos');
    }
};
