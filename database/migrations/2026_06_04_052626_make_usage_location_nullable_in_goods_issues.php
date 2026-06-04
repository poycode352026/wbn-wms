<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN — use raw SQL to drop NOT NULL
            DB::statement('PRAGMA foreign_keys = OFF');

            // Get current table SQL and replace NOT NULL with nullable for usage_location
            $row = DB::select("SELECT sql FROM sqlite_master WHERE type='table' AND name='goods_issues'");
            if (!empty($row)) {
                $sql = $row[0]->sql;

                // Replace various forms of NOT NULL for usage_location column
                $newSql = preg_replace(
                    '/("usage_location"\s+varchar(?:\(\d+\))?)\s+not null/i',
                    '$1',
                    $sql
                );

                if ($newSql !== $sql) {
                    // Recreate table with modified schema
                    $newSql = str_replace(
                        '"goods_issues"',
                        '"goods_issues_new"',
                        $newSql
                    );
                    DB::statement($newSql);
                    DB::statement('INSERT INTO goods_issues_new SELECT * FROM goods_issues');
                    DB::statement('DROP TABLE goods_issues');
                    DB::statement('ALTER TABLE goods_issues_new RENAME TO goods_issues');
                }
            }

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('goods_issues', function (Blueprint $table) {
                $table->string('usage_location')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // No rollback needed — making a column nullable is safe
    }
};
