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
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('driver_name');
            $table->foreignId('driver_id')
                  ->nullable()
                  ->constrained('employees')
                  ->nullOnDelete()
                  ->after('lv_number');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
            $table->string('driver_name', 100)->nullable()->after('lv_number');
        });
    }
};
