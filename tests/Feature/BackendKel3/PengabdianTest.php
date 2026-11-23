<?php

namespace Tests\Feature\BackendKel3;

use App\Models\Dosen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PengabdianTest extends TestCase
{
    use RefreshDatabase;

    private function buatDosen(string $nama = 'Dosen Pengabdian'): Dosen
    {
        $role = DB::table('roles')->where('nama_role', 'dosen')->value('id_role')
            ?? DB::table('roles')->insertGetId(['nama_role' => 'dosen'], 'id_role');

        $user = User::create([
            'id_role' => $role,
            'email' => uniqid().'@test.local',
            'password' => 'password',
            'status' => 1,
        ]);

        return Dosen::create([
            'id_user' => $user->getKey(),
            'nip' => (string) random_int(10 ** 9, 10 ** 10 - 1),
            'nama' => $nama,
        ]);
    }

    /** Kategori berawalan "Pengabdian" (dikenali controller). Dibuat bila belum ada. */
    private function kategoriPengabdian(): int
    {
        $id = DB::table('kategori')->where('nama_kategori', 'like', 'Pengabdian%')->value('id_kategori');
        if ($id) {
            return (int) $id;
        }

        $jenis = DB::table('jenis_kegiatan')->where('nama_jenis_kegiatan', 'Kegiatan Dosen')->value('id_jenis_kegiatan')
            ?? DB::table('jenis_kegiatan')->insertGetId(['nama_jenis_kegiatan' => 'Kegiatan Dosen'], 'id_jenis_kegiatan');

        return (int) DB::table('kategori')->insertGetId([
            'id_jenis_kegiatan' => $jenis,
            'nama_kategori' => 'Pengabdian Masyarakat',
        ], 'id_kategori');
    }

    private function payload(int $idKategori, array $tambah = []): array
    {
        return array_merge([
            'id_kategori' => $idKategori,
            'nama_kegiatan' => 'Pelatihan IoT untuk UMKM Semarang',
            'lokasi' => 'Semarang',
            'tanggal_mulai' => '2026-11-18',
            'tanggal_selesai' => '2026-11-20',
        ], $tambah);
    }

    public function test_dosen_dapat_melihat_daftar_dan_menambah_pengabdian(): void
    {
        $dosen = $this->buatDosen();
        $idKategori = $this->kategoriPengabdian();

        $this->actingAs($dosen->user, 'sanctum')
            ->getJson('/api/v1/dosen/pengabdian')
            ->assertOk();

        $this->actingAs($dosen->user, 'sanctum')
            ->postJson('/api/v1/dosen/pengabdian', $this->payload($idKategori))
            ->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/v1/dosen/pengabdian')->assertStatus(401);
    }

    public function test_nama_kegiatan_wajib_diisi(): void
    {
        $dosen = $this->buatDosen();
        $idKategori = $this->kategoriPengabdian();

        $this->actingAs($dosen->user, 'sanctum')
            ->postJson('/api/v1/dosen/pengabdian', ['id_kategori' => $idKategori])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_pengabdian_dosen_lain_tidak_dapat_dibuka(): void
    {
        $pemilik = $this->buatDosen('Dosen Pemilik');
        $lain = $this->buatDosen('Dosen Lain');
        $idKategori = $this->kategoriPengabdian();

        $idKegiatan = $this->actingAs($pemilik->user, 'sanctum')
            ->postJson('/api/v1/dosen/pengabdian', $this->payload($idKategori))
            ->assertStatus(201)
            ->json('data.id_kegiatan');

        $this->actingAs($lain->user, 'sanctum')
            ->getJson("/api/v1/dosen/pengabdian/{$idKegiatan}")
            ->assertStatus(404);
    }
}
