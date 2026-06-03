<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_requests', function (Blueprint $table) {
            $table->foreignId('goods_issue_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('employee_requests', function (Blueprint $table) {
            $table->dropForeign(['goods_issue_id']);
            $table->dropColumn('goods_issue_id');
        });
    }
};
