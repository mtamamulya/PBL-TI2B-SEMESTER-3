<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const FIELDS = [
        ['Penyelenggara', 'teks', 'NOT NULL'],
        ['Lokasi', 'teks', 'NOT NULL'],
        ['Tanggal Mulai', 'tanggal', 'NULL'],
        ['Tanggal Selesai', 'tanggal', 'NULL'],
    ];

    public function up(): void
    {
        $jenisMahasiswa = DB::table('jenis_kegiatan')
            ->where('nama_jenis_kegiatan', 'Kegiatan Mahasiswa')
            ->value('id_jenis_kegiatan');

        if (! $jenisMahasiswa) {
            return;
        }

        $categories = DB::table('kategori')
            ->where('id_jenis_kegiatan', $jenisMahasiswa)
            ->whereIn('nama_kategori', ['Lomba', 'Volunteer', 'KKL', 'Magang', 'Pengabdian', 'Penelitian'])
            ->get();

        $now = now();

        foreach ($categories as $category) {
            foreach (self::FIELDS as [$input, $jenisInput, $aturan]) {
                $exists = DB::table('isian_kategori')
                    ->where('id_kategori', $category->id_kategori)
                    ->where('input', $input)
                    ->exists();

                if (! $exists) {
                    DB::table('isian_kategori')->insert([
                        'id_kategori' => $category->id_kategori,
                        'input' => $input,
                        'jenis_input' => $jenisInput,
                        'aturan' => $aturan,
                        'jumlah_isian' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::table('isian_kategori')
                ->where('id_kategori', $category->id_kategori)
                ->whereIn('input', ['Tanggal Mulai', 'Tanggal Selesai'])
                ->update(['aturan' => 'NULL', 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Keep category field configuration to avoid losing existing activity answers.
    }
};
