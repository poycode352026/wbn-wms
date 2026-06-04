<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite tidak support ALTER COLUMN — recreate table dengan constraint baru
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement("
                CREATE TABLE goods_issue_photos_new (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    goods_issue_id INTEGER NOT NULL,
                    path       VARCHAR(255) NOT NULL,
                    original_name VARCHAR(255) NULL,
                    stage      VARCHAR(255) CHECK(stage IN ('request','picking','pickup'))
                               NOT NULL DEFAULT 'request',
                    uploaded_by INTEGER NOT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (goods_issue_id) REFERENCES goods_issues(id) ON DELETE CASCADE,
                    FOREIGN KEY (uploaded_by)    REFERENCES users(id)
                )
            ");

            DB::statement('INSERT INTO goods_issue_photos_new SELECT * FROM goods_issue_photos');
            DB::statement('DROP TABLE goods_issue_photos');
            DB::statement('ALTER TABLE goods_issue_photos_new RENAME TO goods_issue_photos');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            // MySQL / PostgreSQL: pakai change() biasa
            Schema::table('goods_issue_photos', function (Blueprint $table) {
                $table->enum('stage', ['request', 'picking', 'pickup'])->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            DB::statement("
                CREATE TABLE goods_issue_photos_new (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    goods_issue_id INTEGER NOT NULL,
                    path       VARCHAR(255) NOT NULL,
                    original_name VARCHAR(255) NULL,
                    stage      VARCHAR(255) CHECK(stage IN ('request','picking'))
                               NOT NULL DEFAULT 'request',
                    uploaded_by INTEGER NOT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    FOREIGN KEY (goods_issue_id) REFERENCES goods_issues(id) ON DELETE CASCADE,
                    FOREIGN KEY (uploaded_by)    REFERENCES users(id)
                )
            ");

            DB::statement('INSERT INTO goods_issue_photos_new
                SELECT * FROM goods_issue_photos WHERE stage != \'pickup\'');
            DB::statement('DROP TABLE goods_issue_photos');
            DB::statement('ALTER TABLE goods_issue_photos_new RENAME TO goods_issue_photos');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('goods_issue_photos', function (Blueprint $table) {
                $table->enum('stage', ['request', 'picking'])->change();
            });
        }
    }
};
