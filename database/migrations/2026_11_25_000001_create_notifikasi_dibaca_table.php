<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyimpan notifikasi mana yang sudah dibuka PER PENGGUNA.
     * Notifikasi sendiri tidak disimpan: ia diturunkan dari tabel timeline,
     * jadi satu baris timeline bisa dilihat pemilik dan anggota, masing-masing punya status baca sendiri.
     */
    public function up(): void
    {
        Schema::create('notifikasi_dibaca', function (Blueprint $table) {
            $table->id('id_notifikasi_dibaca');
            $table->foreignId('id_user')
                ->constrained('users', 'id_user')
                ->cascadeOnDelete();
            $table->foreignId('id_timeline')
                ->constrained('timeline', 'id_timeline')
                ->cascadeOnDelete();
            $table->dateTime('dibaca_pada');

            $table->unique(['id_user', 'id_timeline']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_dibaca');
    }
};
