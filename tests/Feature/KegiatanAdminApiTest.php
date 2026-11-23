<?php

namespace Tests\Feature;

use App\Models\AnggotaKegiatan;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** API Data Kegiatan admin & kaprodi: daftar (hanya diterima), detail (anggota, dokumen, riwayat), tambah, hapus. */
class KegiatanAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private int $kategori;

    private int $jenisDokumen;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $now = now();
        DB::table('jenis_kegiatan')->updateOrInsert(['nama_jenis_kegiatan' => 'Kegiatan Prodi'], ['created_at' => $now, 'updated_at' => $now]);
        $jenis = DB::table('jenis_kegiatan')->where('nama_jenis_kegiatan', 'Kegiatan Prodi')->value('id_jenis_kegiatan');
        $this->kategori = DB::table('kategori')->insertGetId([
            'id_jenis_kegiatan' => $jenis, 'nama_kategori' => 'Kategori Uji', 'kebutuhan' => 'kegiatan',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach (['penyelenggara' => 'teks', 'tanggal_mulai' => 'tanggal'] as $input => $tipe) {
            DB::table('isian_kategori')->insert([
                'id_kategori' => $this->kategori, 'input' => $input, 'jenis_input' => $tipe,
                'aturan' => 'NULL', 'jumlah_isian' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->jenisDokumen = DB::table('jenis_dokumen')->insertGetId([
            'nama_jenis_dokumen' => 'Surat tugas uji', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function user(int $role): User
    {
        return User::query()->where('id_role', $role)->firstOrFail();
    }

    private function masuk(int $role): User
    {
        $u = $this->user($role);
        Sanctum::actingAs($u);

        return $u;
    }

    private function idDosen(User $u): int
    {
        $id = DB::table('dosen')->where('id_user', $u->id_user)->value('id_dosen');

        return (int) ($id ?: DB::table('dosen')->insertGetId([
            'id_user' => $u->id_user, 'nip' => 'N'.random_int(100000, 999999), 'nama' => 'Dosen Uji',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function idMahasiswa(User $u): int
    {
        $id = DB::table('mahasiswa')->where('id_user', $u->id_user)->value('id_mahasiswa');

        return (int) ($id ?: DB::table('mahasiswa')->insertGetId([
            'id_user' => $u->id_user, 'nim' => 'M'.random_int(100000, 999999), 'nama' => 'Mahasiswa Uji',
            'tahun_masuk' => 2023, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function kegiatan(User $pemilik, string $status, string $nama = 'Kegiatan Uji'): Kegiatan
    {
        $k = Kegiatan::query()->create(['id_kategori' => $this->kategori, 'id_user' => $pemilik->id_user, 'nama_kegiatan' => $nama]);
        Timeline::query()->create(['id_kegiatan' => $k->id_kegiatan, 'status' => 'diajukan', 'tanggal_status' => now()->subDays(2)]);
        Timeline::query()->create(['id_kegiatan' => $k->id_kegiatan, 'status' => $status, 'tanggal_status' => now()->subDay()]);

        return $k;
    }

    private function payload(array $ubah = []): array
    {
        return $ubah + [
            'jenis_kegiatan' => 'Kegiatan Prodi',
            'id_kategori' => $this->kategori,
            'nama_kegiatan' => 'Workshop Kurikulum',
            'penyelenggara' => 'Prodi TRK',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-06',
            'anggota' => ['Dosen:'.$this->idDosen($this->user(3))],
        ];
    }

    public function test_tanpa_token_ditolak_dan_dosen_tidak_boleh(): void
    {
        $this->getJson('/api/v1/admin/kegiatan')->assertStatus(401);

        $this->masuk(3);
        $this->getJson('/api/v1/admin/kegiatan')->assertStatus(403);
    }

    public function test_daftar_hanya_status_terakhir_diterima_dan_jenis_dari_role(): void
    {
        $this->kegiatan($this->user(3), 'diterima', 'Kegiatan Dosen A');
        $this->kegiatan($this->user(4), 'diterima', 'Kegiatan Mahasiswa A');
        $this->kegiatan($this->user(1), 'diterima', 'Kegiatan Prodi A');
        $this->kegiatan($this->user(3), 'direvisi', 'Kegiatan Direvisi');
        $this->masuk(2);

        $r = $this->getJson('/api/v1/admin/kegiatan')->assertOk();
        $this->assertSame(
            ['Kegiatan Dosen A', 'Kegiatan Mahasiswa A', 'Kegiatan Prodi A'],
            collect($r->json('data'))->pluck('nama')->sort()->values()->all()
        );
        $this->assertSame('dosen', collect($r->json('data'))->firstWhere('nama', 'Kegiatan Dosen A')['jenis']);
        $this->assertSame('prodi', collect($r->json('data'))->firstWhere('nama', 'Kegiatan Prodi A')['jenis']);

        $this->getJson('/api/v1/admin/kegiatan?jenis=mahasiswa')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/kegiatan/ringkasan')->assertOk()
            ->assertJsonPath('data.jumlah.semua', 3)->assertJsonPath('data.jumlah.dosen', 1);
    }

    public function test_detail_memuat_anggota_dokumen_dan_riwayat(): void
    {
        $dosen = $this->user(3);
        $k = $this->kegiatan($dosen, 'diterima');
        AnggotaKegiatan::query()->create(['id_kegiatan' => $k->id_kegiatan, 'id_user' => $dosen->id_user, 'peran' => 'ketua']);
        DB::table('dokumen')->insert([
            'id_jenis_dokumen' => $this->jenisDokumen, 'id_kegiatan' => $k->id_kegiatan, 'nama_file' => 'Surat.pdf',
            'tipe_dokumen' => 'pdf', 'link_dokumen' => 'https://contoh.test/surat.pdf', 'is_lampiran' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->masuk(1);

        $r = $this->getJson('/api/v1/admin/kegiatan/'.$k->id_kegiatan)->assertOk();
        $r->assertJsonPath('data.anggota.0.status', 'Ketua')->assertJsonPath('data.anggota.0.pengaju', true);
        $r->assertJsonPath('data.dokumen.0.nama', 'Surat.pdf')
            ->assertJsonPath('data.dokumen.0.kategori', 'Surat tugas uji')
            ->assertJsonPath('data.dokumen.0.url', 'https://contoh.test/surat.pdf');
        $r->assertJsonCount(2, 'data.riwayat')->assertJsonPath('data.riwayat.1.status', 'Diterima');
    }

    public function test_detail_kegiatan_belum_diterima_404(): void
    {
        $k = $this->kegiatan($this->user(3), 'direvisi');
        $this->masuk(1);

        $this->getJson('/api/v1/admin/kegiatan/'.$k->id_kegiatan)->assertStatus(404);
    }

    public function test_admin_tambah_kegiatan_prodi_langsung_diterima(): void
    {
        $admin = $this->masuk(1);

        $r = $this->postJson('/api/v1/admin/kegiatan', $this->payload())->assertCreated();
        $r->assertJsonPath('data.jenis', 'prodi')->assertJsonPath('data.pengaju', $admin->email)->assertJsonPath('data.status', 'Disetujui');
        $r->assertJsonPath('data.anggota.0.peran', 'anggota');
        // kategori uji tidak punya isian tanggal_selesai -> peringatan, bukan galat
        $this->assertCount(1, $r->json('peringatan'));

        $id = $r->json('data.id');
        $this->assertSame('diterima', Kegiatan::query()->findOrFail($id)->statusTerkini->status);
        $this->getJson('/api/v1/admin/kegiatan?jenis=prodi')->assertJsonCount(1, 'data');
    }

    public function test_kaprodi_tambah_kegiatan_dosen_pemilik_dosen_pertama(): void
    {
        $this->masuk(2);
        $dosen = $this->user(3);

        $r = $this->postJson('/api/v1/admin/kegiatan', $this->payload([
            'jenis_kegiatan' => 'Kegiatan Dosen',
            'anggota' => ['Dosen:'.$this->idDosen($dosen), 'Mahasiswa:'.$this->idMahasiswa($this->user(4))],
        ]))->assertCreated();

        $r->assertJsonPath('data.jenis', 'dosen');
        $this->assertSame((int) $dosen->id_user, (int) Kegiatan::query()->findOrFail($r->json('data.id'))->id_user);
        $this->assertSame('ketua', collect($r->json('data.anggota'))->firstWhere('id_user', $dosen->id_user)['peran']);
    }

    public function test_tambah_dengan_dokumen_file_dan_tautan_lewat_kunci_berkurung(): void
    {
        $this->masuk(1);

        $r = $this->post('/api/v1/admin/kegiatan', $this->payload([
            'dokumen' => [
                ['nama' => 'Surat tugas', 'jenis' => 'Surat tugas uji', 'file' => UploadedFile::fake()->create('s.pdf', 50, 'application/pdf')],
                ['nama' => 'Folder foto', 'jenis' => 'Surat tugas uji', 'tautan' => 'https://contoh.test/foto'],
            ],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $r->assertJsonCount(2, 'data.dokumen');
        $urls = collect($r->json('data.dokumen'))->pluck('url');
        $this->assertTrue($urls->contains('https://contoh.test/foto'));
        $this->assertCount(1, Storage::disk('public')->allFiles('kegiatan'));
    }

    public function test_validasi_dan_rollback(): void
    {
        $this->masuk(1);

        $this->postJson('/api/v1/admin/kegiatan', $this->payload(['anggota' => []]))->assertStatus(422);
        $this->postJson('/api/v1/admin/kegiatan', $this->payload(['tanggal_selesai' => '2026-10-01']))->assertStatus(422);
        $this->postJson('/api/v1/admin/kegiatan', $this->payload(['id_kategori' => 999999]))->assertStatus(422);
        // Kegiatan Mahasiswa tanpa anggota mahasiswa
        $this->postJson('/api/v1/admin/kegiatan', $this->payload(['jenis_kegiatan' => 'Kegiatan Mahasiswa']))->assertStatus(422);
        // jenis dokumen tidak dikenal
        $this->postJson('/api/v1/admin/kegiatan', $this->payload([
            'dokumen' => [['nama' => 'X', 'jenis' => 'Tidak ada', 'tautan' => 'https://contoh.test/x']],
        ]))->assertStatus(422);

        $this->assertSame(0, Kegiatan::query()->count());
    }

    public function test_kaprodi_tidak_boleh_hapus_admin_boleh(): void
    {
        $k = $this->kegiatan($this->user(3), 'diterima');

        $this->masuk(2);
        $this->deleteJson('/api/v1/admin/kegiatan/'.$k->id_kegiatan)->assertStatus(403);

        $this->masuk(1);
        $this->deleteJson('/api/v1/admin/kegiatan/'.$k->id_kegiatan)->assertOk();
        $this->assertNull(Kegiatan::query()->find($k->id_kegiatan));
    }
}
