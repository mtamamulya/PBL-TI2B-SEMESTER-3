<?php

namespace Tests\Feature\Api;

use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TahunAkademikApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $idRole): User
    {
        $nama = [1 => 'admin', 2 => 'kaprodi', 3 => 'dosen', 4 => 'mahasiswa'][$idRole];
        DB::table('roles')->insertOrIgnore(['id_role' => $idRole, 'nama_role' => $nama]);

        return User::firstOrCreate(
            ['email' => "{$nama}@trk.ac.id"],
            ['id_role' => $idRole, 'password' => 'password', 'status' => true]
        );
    }

    private function sebagai(int $idRole): static
    {
        return $this->actingAs($this->user($idRole), 'sanctum');
    }

    public function test_daftar_dan_aktif_dapat_diakses_publik(): void
    {
        $this->getJson('/api/v1/tahun-akademik/aktif')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Belum ada tahun akademik yang aktif.');

        $lama = TahunAkademik::create(['tahun_akademik' => 'Ganjil 2025/2026', 'status' => false]);
        $aktif = TahunAkademik::create(['tahun_akademik' => 'Genap 2025/2026', 'status' => true]);

        $res = $this->getJson('/api/v1/tahun-akademik')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->assertSame($aktif->id_tahun_akademik, $res->json('data.0.id_tahun_akademik'));
        $this->assertSame($lama->id_tahun_akademik, $res->json('data.1.id_tahun_akademik'));

        $this->getJson('/api/v1/tahun-akademik/aktif')
            ->assertOk()
            ->assertJsonPath('data.tahun_akademik', 'Genap 2025/2026');
    }

    public function test_tambah_ditolak_tanpa_token_atau_non_admin(): void
    {
        $payload = ['semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027'];

        $this->postJson('/api/v1/tahun-akademik', $payload)->assertStatus(401);
        $this->sebagai(2)->postJson('/api/v1/tahun-akademik', $payload)->assertStatus(403);
        $this->sebagai(3)->postJson('/api/v1/tahun-akademik', $payload)->assertStatus(403);
        $this->sebagai(4)->postJson('/api/v1/tahun-akademik', $payload)->assertStatus(403);
    }

    public function test_admin_dapat_menambah_dan_otomatis_mengaktifkan_yang_baru(): void
    {
        $lama = TahunAkademik::create(['tahun_akademik' => 'Genap 2025/2026', 'status' => true]);

        $res = $this->sebagai(1)
            ->postJson('/api/v1/tahun-akademik', [
                'semester' => 'Ganjil',
                'tahun_ajaran' => '2026/2027',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tahun_akademik', 'Ganjil 2026/2027');

        $this->assertTrue((bool) $res->json('data.status'));

        $this->assertDatabaseHas('tahun_akademik', [
            'id_tahun_akademik' => $lama->id_tahun_akademik,
            'status' => false,
        ]);
        $this->assertDatabaseHas('tahun_akademik', [
            'tahun_akademik' => 'Ganjil 2026/2027',
            'status' => true,
        ]);
    }

    public function test_validasi_tambah_tahun_akademik(): void
    {
        TahunAkademik::create(['tahun_akademik' => 'Ganjil 2026/2027', 'status' => true]);

        // Semester tidak valid
        $this->sebagai(1)
            ->postJson('/api/v1/tahun-akademik', ['semester' => 'Antara', 'tahun_ajaran' => '2026/2027'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('semester');

        // Format tahun_ajaran salah
        $this->sebagai(1)
            ->postJson('/api/v1/tahun-akademik', ['semester' => 'Genap', 'tahun_ajaran' => '2026-2027'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tahun_ajaran');

        // Selisih tahun bukan +1
        $this->sebagai(1)
            ->postJson('/api/v1/tahun-akademik', ['semester' => 'Genap', 'tahun_ajaran' => '2026/2028'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tahun_ajaran');

        // Duplikat label
        $this->sebagai(1)
            ->postJson('/api/v1/tahun-akademik', ['semester' => 'Ganjil', 'tahun_ajaran' => '2026/2027'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tahun_ajaran');
    }
}
