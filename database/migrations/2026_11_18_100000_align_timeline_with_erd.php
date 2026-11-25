<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan tabel timeline dengan ERD final + kolom pelaku (id_user).
 *
 * Dibuat kondisional karena database yang sedang dipakai tim belum tentu sama dengan ERD:
 * jika tabel belum ada dibuat sesuai ERD, jika sudah ada hanya ditambah kolom id_user.
 * Setelah audit skema selesai, ganti dengan satu migrasi yang pasti.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timeline')) {
            Schema::create('timeline', function (Blueprint $table) {
                $table->id('id_timeline');
                if (DB::getDriverName() === 'sqlite') {
                    $table->unsignedBigInteger('id_kegiatan');
                } else {
                    $table->foreignId('id_kegiatan')->constrained('kegiatan', 'id_kegiatan')->cascadeOnDelete();
                }
                $table->enum('status', ['diajukan', 'direvisi', 'ditolak', 'direview', 'diterima', 'selesai']);
                $table->timestamp('tanggal_status')->useCurrent();
                $table->text('catatan_revisi')->nullable();
                $table->unsignedBigInteger('id_user')->nullable();      // pelaku perubahan, null = sistem
                $table->index(['id_kegiatan', 'id_timeline']);
            });

            return;
        }

        if (! Schema::hasColumn('timeline', 'id_user')) {
            Schema::table('timeline', function (Blueprint $table) {
                $table->unsignedBigInteger('id_user')->nullable()->after('catatan_revisi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('timeline') && Schema::hasColumn('timeline', 'id_user')) {
            Schema::table('timeline', fn (Blueprint $table) => $table->dropColumn('id_user'));
        }
    }
};
