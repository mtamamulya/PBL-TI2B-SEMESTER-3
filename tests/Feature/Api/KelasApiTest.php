<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KelasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_dan_detail_kelas_dapat_diakses_publik(): void
    {
        $idProdi = DB::table('prodi')->insertGetId([
            'kode_prodi' => 'D4-TI',
            'nama_prodi' => 'Teknik Informatika',
            'jenjang' => 'D4',
        ]);
        $idTingkat = DB::table('tingkat')->insertGetId([
            'tingkat' => 3,
            'keterangan' => 'Tingkat 3',
        ]);
        $idKelasB = DB::table('kelas')->insertGetId([
            'jenis_kelas' => 'B',
            'keterangan' => 'Kelas B',
        ]);
        $idTaLama = DB::table('tahun_akademik')->insertGetId([
            'tahun_akademik' => 'Genap 2025/2026',
            'status' => false,
        ]);
        $idTaAktif = DB::table('tahun_akademik')->insertGetId([
            'tahun_akademik' => 'Ganjil 2026/2027',
            'status' => true,
        ]);

        DB::table('enrollment_tingkat_kelas')->insert([
            'id_prodi' => $idProdi,
            'id_tingkat' => $idTingkat,
            'id_kelas' => $idKelasB,
            'id_tahun_akademik' => $idTaLama,
        ]);
        $idEtkAktif = DB::table('enrollment_tingkat_kelas')->insertGetId([
            'id_prodi' => $idProdi,
            'id_tingkat' => $idTingkat,
            'id_kelas' => $idKelasB,
            'id_tahun_akademik' => $idTaAktif,
        ]);

        // Ambil semua & pastikan yang aktif muncul pertama dengan format nama_kelas TI-3B
        $this->getJson('/api/v1/kelas')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id_enrollment_tingkat_kelas', $idEtkAktif)
            ->assertJsonPath('data.0.nama_kelas', 'TI-3B')
            ->assertJsonPath('data.0.status_tahun_akademik', true);

        // Filter aktif=1
        $this->getJson('/api/v1/kelas?aktif=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        // Detail satu kelas
        $this->getJson("/api/v1/kelas/{$idEtkAktif}")
            ->assertOk()
            ->assertJsonPath('data.nama_kelas', 'TI-3B')
            ->assertJsonPath('data.tahun_akademik', 'Ganjil 2026/2027');

        $this->getJson('/api/v1/kelas/99999')->assertStatus(404);
    }
}
