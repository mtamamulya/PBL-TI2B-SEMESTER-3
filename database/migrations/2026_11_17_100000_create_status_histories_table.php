<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat status bersama untuk semua alur (kegiatan, berita, dst).
 * Tabel ini hanya ditambah, tidak pernah diubah atau dihapus (jejak audit).
 * Status terkini sebuah data = baris terakhir (id terbesar) untuk workflow + subject_id.
 *
 * Cek dulu apakah repo sudah punya tabel serupa sebelum menjalankan migrasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->string('workflow', 40);                       // kunci alur: kegiatan, berita, ...
            $table->unsignedBigInteger('subject_id');             // id data yang berstatus
            $table->string('from_status', 40)->nullable();        // null = status awal
            $table->string('status', 40);                         // status tujuan (kode, bukan label)
            $table->unsignedBigInteger('actor_id')->nullable();   // null = proses sistem
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workflow', 'subject_id', 'id'], 'status_histories_subject_idx');
            $table->index(['workflow', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
