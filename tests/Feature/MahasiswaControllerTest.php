<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MahasiswaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['data' => []], 200),
        ]);

        if (Schema::hasTable('roles')) {
            $roleRow = ['id_role' => 4];
            if (Schema::hasColumn('roles', 'nama_role')) {
                $roleRow['nama_role'] = 'Mahasiswa';
            } elseif (Schema::hasColumn('roles', 'nama')) {
                $roleRow['nama'] = 'Mahasiswa';
            }
            DB::table('roles')->insertOrIgnore($roleRow);
        }

        DB::table('tahun_akademik')->insert([
            'tahun_akademik' => '2026/2027 Ganjil',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function mahasiswaUser(string $tahunMasuk = '2024'): User
    {
        $user = User::create([
            'email' => 'mhs-'.uniqid().'@trk.ac.id',
            'password' => bcrypt('password'),
            'id_role' => 4,
            'status' => true,
        ]);

        $userId = $user->getKey();

        DB::table('mahasiswa')->insert([
            'id_user' => $userId,
            'nim' => '433'.substr((string) $userId, -6),
            'nama' => 'Mahasiswa Uji',
            'tahun_masuk' => $tahunMasuk,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_halaman_mata_kuliah_semester_berjalan_bisa_diakses(): void
    {
        $response = $this->actingAs($this->mahasiswaUser('2025'))
            ->get('/mahasiswa/mata-kuliah');

        $response->assertStatus(200);
        $response->assertViewIs('mahasiswa.mahasiswa.mata-kuliah');
        $response->assertViewHas('pesanKosong', 'Belum ada data untuk ditampilkan');
        $response->assertViewHas('ringkasan', fn (array $r) => ($r['semester_ke'] ?? null) === 3);
    }

    public function test_tab_katalog_dan_riwayat_bisa_diakses(): void
    {
        $user = $this->mahasiswaUser('2024');

        $this->actingAs($user)
            ->get('/mahasiswa/mata-kuliah/katalog')
            ->assertStatus(200)
            ->assertViewIs('mahasiswa.mahasiswa.mata-kuliah')
            ->assertViewHas('pesanKosong', 'Belum ada data untuk ditampilkan');

        $this->actingAs($user)
            ->get('/mahasiswa/mata-kuliah/riwayat')
            ->assertStatus(200)
            ->assertViewIs('mahasiswa.mahasiswa.mata-kuliah')
            ->assertViewHas('pesanKosong', 'Belum ada data untuk ditampilkan');
    }

    public function test_filter_pencarian_mata_kuliah_berjalan(): void
    {
        $response = $this->actingAs($this->mahasiswaUser('2024'))
            ->get('/mahasiswa/mata-kuliah/katalog?q=Jaringan');

        $response->assertStatus(200);
        $response->assertViewIs('mahasiswa.mahasiswa.mata-kuliah');
        $response->assertViewHas('pesanKosong', 'Belum ada data untuk ditampilkan');
    }

    public function test_tab_tidak_valid_mengembalikan_404(): void
    {
        $this->actingAs($this->mahasiswaUser('2024'))
            ->get('/mahasiswa/mata-kuliah/tab-ngasal')
            ->assertStatus(404);
    }
}
