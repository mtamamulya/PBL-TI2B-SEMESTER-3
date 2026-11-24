<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gambar_berita', function (Blueprint $table) {
            $table->id('id_gambar_berita');
            $table->foreignId('id_berita')
                ->nullable()
                ->constrained('berita', 'id_berita')
                ->cascadeOnDelete();
            $table->foreignId('id_dokumen')
                ->nullable()
                ->constrained('dokumen', 'id_dokumen')
                ->nullOnDelete();
            $table->foreignId('id_user')
                ->nullable()
                ->constrained('users', 'id_user')
                ->nullOnDelete();
            $table->string('nama_file', 255);
            $table->string('path_gambar', 500);
            $table->enum('sumber', ['upload', 'kegiatan'])->default('upload');
            $table->timestamps();

            $table->index(['id_berita', 'sumber']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gambar_berita');
    }
};
