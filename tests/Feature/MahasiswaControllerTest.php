<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MahasiswaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '127.0.0.1:8001/*' => Http::response([], 500),
        ]);

        DB::table('tahun_akademik')->insert([
            'tahun_akademik' => '2026/2027 Ganjil',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mahasiswaUser(): User
    {
        $user = new User;
        $user->id_role = 4;
        $user->status = true;

        return $user;
    }

    public function test_halaman_mata_kuliah_semester_berjalan_bisa_diakses(): void
    {
        $response = $this->actingAs($this->mahasiswaUser())
            ->get('/mahasiswa/mata-kuliah');

        $response->assertStatus(200);
        $response->assertViewIs('mahasiswa.mahasiswa.mata-kuliah');
        $response->assertSee('Kecerdasan Buatan');
    }

    public function test_tab_katalog_dan_riwayat_bisa_diakses(): void
    {
        $user = $this->mahasiswaUser();

        $this->actingAs($user)
            ->get('/mahasiswa/mata-kuliah/katalog')
            ->assertStatus(200)
            ->assertViewIs('mahasiswa.mahasiswa.mata-kuliah');

        $this->actingAs($user)
            ->get('/mahasiswa/mata-kuliah/riwayat')
            ->assertStatus(200)
            ->assertViewIs('mahasiswa.mahasiswa.mata-kuliah');
    }

    public function test_filter_pencarian_mata_kuliah_berjalan(): void
    {
        $response = $this->actingAs($this->mahasiswaUser())
            ->get('/mahasiswa/mata-kuliah/katalog?q=Jaringan');

        $response->assertStatus(200);
        $response->assertViewIs('mahasiswa.mahasiswa.mata-kuliah');
    }

    public function test_tab_tidak_valid_mengembalikan_404(): void
    {
        $this->actingAs($this->mahasiswaUser())
            ->get('/mahasiswa/mata-kuliah/tab-ngasal')
            ->assertStatus(404);
    }
}
