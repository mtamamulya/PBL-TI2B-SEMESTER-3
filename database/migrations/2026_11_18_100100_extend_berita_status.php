<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ERD final: berita.status enum (publish, draft). UI berita punya lima status.
 * Menambah diajukan, direvisi, ditolak. Kode 'publish' dipertahankan (label UI: Dipublikasikan).
 * Hanya untuk MySQL/MariaDB (tes di SQLite melewati migrasi ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('berita') || ! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("ALTER TABLE berita MODIFY status ENUM('draft','diajukan','direvisi','publish','ditolak') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('berita') || ! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('berita')->whereIn('status', ['diajukan', 'direvisi', 'ditolak'])->update(['status' => 'draft']);
        DB::statement("ALTER TABLE berita MODIFY status ENUM('publish','draft') NOT NULL DEFAULT 'draft'");
    }
};
