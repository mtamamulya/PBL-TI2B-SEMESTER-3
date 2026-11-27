<?php

namespace Tests\Feature\BackendKel3;

use App\Models\Dosen;
use App\Models\User;
use Database\Seeders\MasterRisetDosenSeeder;
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
        $id = DB::table('kategori')
            ->join('jenis_kegiatan', 'jenis_kegiatan.id_jenis_kegiatan', '=', 'kategori.id_jenis_kegiatan')
            ->where('jenis_kegiatan.nama_jenis_kegiatan', 'Kegiatan Dosen')
            ->where('kategori.nama_kategori', 'like', 'Pengabdian%')
            ->value('kategori.id_kategori');
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

    public function test_master_dapat_diulang_dan_form_bersama_menyimpan_kegiatan_dosen(): void
    {
        $this->seed(MasterRisetDosenSeeder::class);
        $this->seed(MasterRisetDosenSeeder::class);
        $dosen = $this->buatDosen();
        $this->actingAs($dosen->user)->get('/dosen/kegiatan/create')->assertOk()->assertSee($dosen->nama);
        $master = $this->withHeader('Origin', 'http://localhost')->getJson('/api/v1/kategori-kegiatan?jenis=Kegiatan+Dosen')
            ->assertOk()->assertJsonCount(2, 'data')->json('data');
        foreach (['Pengabdian' => 'Pengabdian Kemitraan Masyarakat', 'Penelitian' => 'Penelitian Terapan'] as $kategori => $skim) {
            $id = $this->actingAs($dosen->user, 'sanctum')->postJson('/api/v1/dosen/'.strtolower($kategori), [
                'id_kategori' => collect($master)->firstWhere('nama_kategori', $kategori)['id_kategori'], 'nama_kegiatan' => 'Uji form', 'penyelenggara' => 'TRK',
                'tanggal_mulai' => '2026-11-25', 'tanggal_selesai' => '2026-11-26',
                'detail' => ['skim' => $skim, 'kelompok_bidang' => 'Pendidikan', 'lokasi' => 'Semarang',
                    'tahun_pelaksanaan_ke' => 1, 'lama_kegiatan' => 1, 'dana_perguruan_tinggi' => 10000],
                'dokumen' => [['nama' => 'Proposal', 'jenis' => 'Surat Tugas', 'tautan' => 'https://example.org/proposal']],
            ])->assertCreated()->json('data.id_kegiatan');
            $this->assertDatabaseHas('dokumen', ['id_kegiatan' => $id, 'nama_file' => 'Proposal']);
            $this->assertDatabaseHas('anggota_kegiatan', ['id_kegiatan' => $id, 'id_user' => $dosen->id_user, 'peran' => 'ketua']);
        }
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

    public function test_halaman_daftar_pengabdian_membaca_data_dari_api(): void
    {
        $dosen = $this->buatDosen();
        $idKategori = $this->kategoriPengabdian();

        $this->actingAs($dosen->user, 'sanctum')
            ->postJson('/api/v1/dosen/pengabdian', $this->payload($idKategori))
            ->assertStatus(201);

        $this->actingAs($dosen->user)
            ->get('/pengabdian')
            ->assertOk()
            ->assertSee('Pelatihan IoT untuk UMKM Semarang');
    }

    public function test_halaman_detail_pengabdian_membaca_data_dari_api(): void
    {
        $dosen = $this->buatDosen();
        $idKategori = $this->kategoriPengabdian();

        $id = $this->actingAs($dosen->user, 'sanctum')
            ->postJson('/api/v1/dosen/pengabdian', $this->payload($idKategori))
            ->assertStatus(201)
            ->json('data.id_kegiatan');

        $this->actingAs($dosen->user)
            ->get("/pengabdian/{$id}")
            ->assertOk()
            ->assertSee('Pelatihan IoT untuk UMKM Semarang');
    }

    public function test_halaman_detail_dosen_lain_tidak_ditemukan(): void
    {
        $pemilik = $this->buatDosen('Dosen Pemilik');
        $lain = $this->buatDosen('Dosen Lain');
        $idKategori = $this->kategoriPengabdian();

        $id = $this->actingAs($pemilik->user, 'sanctum')
            ->postJson('/api/v1/dosen/pengabdian', $this->payload($idKategori))
            ->assertStatus(201)
            ->json('data.id_kegiatan');

        $this->actingAs($lain->user)
            ->get("/pengabdian/{$id}")
            ->assertNotFound();
    }

    public function test_form_tambah_pengabdian_dapat_dibuka(): void
    {
        $dosen = $this->buatDosen();
        $this->kategoriPengabdian();

        $this->actingAs($dosen->user)
            ->get('/pengabdian/create')
            ->assertOk()
            ->assertSee('Tambah Pengabdian');
    }

    public function test_form_tambah_pengabdian_menyimpan_lewat_api(): void
    {
        $dosen = $this->buatDosen();
        $this->kategoriPengabdian();

        $this->actingAs($dosen->user)
            ->post('/pengabdian', [
                'nama_kegiatan' => 'Pelatihan Web Desa',
                'tanggal_mulai' => '2026-11-18',
                'tanggal_selesai' => '2026-11-20',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kegiatan', ['nama_kegiatan' => 'Pelatihan Web Desa']);
    }

    public function test_form_tambah_pengabdian_menolak_nama_kosong(): void
    {
        $dosen = $this->buatDosen();
        $this->kategoriPengabdian();

        $this->actingAs($dosen->user)
            ->post('/pengabdian', ['tanggal_mulai' => '2026-11-18'])
            ->assertSessionHasErrors();
    }

    public function test_form_ubah_pengabdian_dapat_dibuka_dan_disimpan(): void
    {
        $dosen = $this->buatDosen();
        $idKategori = $this->kategoriPengabdian();

        $id = $this->actingAs($dosen->user, 'sanctum')
            ->postJson('/api/v1/dosen/pengabdian', $this->payload($idKategori))
            ->assertStatus(201)
            ->json('data.id_kegiatan');

        $this->actingAs($dosen->user)
            ->get("/pengabdian/{$id}/edit")
            ->assertOk()
            ->assertSee('Pelatihan IoT untuk UMKM Semarang');

        $this->actingAs($dosen->user)
            ->put("/pengabdian/{$id}", ['nama_kegiatan' => 'Judul Baru', 'id_kategori' => $idKategori])
            ->assertRedirect(route('pengabdian.show', $id));

        $this->assertDatabaseHas('kegiatan', ['id_kegiatan' => $id, 'nama_kegiatan' => 'Judul Baru']);
    }
}
