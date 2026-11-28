<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentActivityCategorySeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_activity_type_and_six_card_categories_are_created(): void
    {
        $idJenisMahasiswa = DB::table('jenis_kegiatan')
            ->where('nama_jenis_kegiatan', 'Kegiatan Mahasiswa')
            ->value('id_jenis_kegiatan');

        $this->assertNotNull($idJenisMahasiswa);
        $this->assertSame(
            ['Lomba', 'Volunteer', 'KKL', 'Magang', 'Pengabdian', 'Penelitian'],
            DB::table('kategori')
                ->where('id_jenis_kegiatan', $idJenisMahasiswa)
                ->orderBy('id_kategori')
                ->pluck('nama_kategori')
                ->all()
        );

        foreach (['Lomba', 'Volunteer', 'KKL', 'Magang', 'Pengabdian', 'Penelitian'] as $namaKategori) {
            $idKategori = DB::table('kategori')
                ->where('id_jenis_kegiatan', $idJenisMahasiswa)
                ->where('nama_kategori', $namaKategori)
                ->value('id_kategori');

            $this->assertNotNull($idKategori);
            $this->assertSame(
                ['Penyelenggara', 'Lokasi', 'Tanggal Mulai', 'Tanggal Selesai'],
                DB::table('isian_kategori')
                    ->where('id_kategori', $idKategori)
                    ->orderBy('id_isian_kategori')
                    ->pluck('input')
                    ->all()
            );
        }
    }
}
