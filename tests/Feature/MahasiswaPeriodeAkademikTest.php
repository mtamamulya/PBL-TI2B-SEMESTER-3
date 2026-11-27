<?php

namespace Tests\Feature;

use App\BackendKel2\Akademik\PeriodeAkademik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MahasiswaPeriodeAkademikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Memalsukan respons API luar (Kelompok 1 di port 8001) agar tes aman tanpa server luar
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'data' => [
                    [
                        'id' => 1,
                        'kode_mk' => 'TIB101',
                        'nama_mk' => 'Pemrograman Web',
                        'sks_teori' => 2,
                        'sks_praktik' => 1,
                        'semester' => 1,
                    ],
                ],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 100,
                    'total' => 1,
                    'last_page' => 1,
                ],
            ], 200),
        ]);

        // 2. Memasukkan data dummy tahun akademik aktif agar helper PeriodeAkademik bisa membacanya
        DB::table('tahun_akademik')->insert([
            'tahun_akademik' => '2026/2027 Ganjil',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function endpoint_semester_berjalan_berhasil_memuat_periode_akademik()
    {
        $periode = PeriodeAkademik::sekarang();
        $this->assertNotNull($periode);

        // Tes akses ke endpoint semester berjalan
        $response = $this->getJson('/api/v1/mahasiswa/semester-berjalan');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'periode_akademik',
                'summary',
                'data_mata_kuliah',
                'dosen_pengampu',
            ]);
    }

    #[Test]
    public function endpoint_riwayat_akademik_berhasil_diakses()
    {
        // Tes akses ke endpoint riwayat akademik
        $response = $this->getJson('/api/v1/mahasiswa/riwayat');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'periode_aktif',
                'data',
                'dosen_pengampu',
            ]);
    }
}
