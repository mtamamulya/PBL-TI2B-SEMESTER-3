<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MahasiswaMatkulApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Memalsukan respons API luar (Kelompok 1)
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
    }

    #[Test]
    public function endpoint_katalog_mk_dapat_diakses()
    {
        $response = $this->getJson('/api/v1/mahasiswa/katalog-mk');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
                'meta',
            ]);
    }

    #[Test]
    public function endpoint_semester_berjalan_dapat_diakses()
    {
        $response = $this->getJson('/api/v1/mahasiswa/semester-berjalan');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'periode_akademik',
                'summary',
                'data_mata_kuliah',
                'meta',
                'dosen_pengampu',
            ]);
    }

    #[Test]
    public function endpoint_riwayat_akademik_dapat_diakses()
    {
        $response = $this->getJson('/api/v1/mahasiswa/riwayat');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'periode_aktif',
                'data',
                'meta',
                'dosen_pengampu',
            ]);
    }
}
