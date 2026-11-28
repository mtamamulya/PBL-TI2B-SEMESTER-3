<?php

namespace Tests\Feature\Api;

use App\Models\Akreditasi;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Endpoint tulis (POST/PUT/PATCH/DELETE) kurikulum, mata kuliah, dan akreditasi: token, peran, validasi, hasil. */
class TulisApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Prodi::query()->create([
            'kode_prodi' => 'PRODI-UJI',
            'nama_prodi' => 'Prodi Uji',
            'jenjang' => 'D4',
        ]);
        Storage::fake('public');
    }

    /** Masuk sebagai akun seeder dengan peran itu (1 admin, 2 kaprodi, 3 dosen, 4 mahasiswa). */
    private function masukSebagai(int $peran): void
    {
        $user = User::query()->where('id_role', $peran)->where('status', true)->first();
        if (! $user) {
            $this->markTestSkipped("Seeder belum punya akun aktif dengan id_role {$peran}.");
        }
        Sanctum::actingAs($user, ['*']);
    }

    private function perluProdi(): void
    {
        if (! Prodi::query()->exists()) {
            $this->markTestSkipped('Seeder belum punya data prodi.');
        }
    }

    private function buatKurikulum(string $nama = 'Kurikulum Uji', bool $aktif = true): Kurikulum
    {
        $this->perluProdi();
        $k = new Kurikulum;
        $k->id_prodi = Prodi::query()->orderBy('id_prodi')->value('id_prodi');
        $k->nama_kurikulum = $nama;
        $k->status = $aktif;
        $k->save();

        return $k;
    }

    private function dataMk(array $ubah = []): array
    {
        return $ubah + [
            'semester' => 1, 'kode_mk' => 'TRK101', 'nama_mk' => 'Pemrograman Dasar',
            'sks_teori' => 2, 'sks_praktik' => 1, 'jam_teori' => 2, 'jam_praktek' => 3,
        ];
    }

    private function buatAkreditasi(string $lembaga, bool $aktif): Akreditasi
    {
        $a = new Akreditasi;
        $a->id_user = User::query()->value('id_user');
        $a->keterangan = $lembaga;
        $a->status = $aktif;
        $a->save();

        return $a;
    }

    // ---- penjaga token dan peran ----

    public function test_tanpa_token_ditolak_401_json(): void
    {
        $this->postJson('/api/v1/kurikulum', [])->assertStatus(401)->assertJsonPath('success', false);
        $this->postJson('/api/v1/akreditasi', [])->assertStatus(401);
        $this->deleteJson('/api/v1/mata-kuliah/1')->assertStatus(401);
    }

    public function test_dosen_dan_mahasiswa_ditolak_403(): void
    {
        foreach ([3, 4] as $peran) {
            $this->masukSebagai($peran);
            $this->postJson('/api/v1/kurikulum', ['nama_kurikulum' => 'X', 'tahun_terbit' => 2026])->assertStatus(403);
            $this->postJson('/api/v1/kurikulum/1/mata-kuliah', $this->dataMk())->assertStatus(403);
            $this->postJson('/api/v1/akreditasi', ['lembaga' => 'BAN-PT', 'tahun_terbit' => 2024])->assertStatus(403);
        }
    }

    public function test_kaprodi_tidak_boleh_menulis_akreditasi(): void
    {
        $this->masukSebagai(2);

        $this->postJson('/api/v1/akreditasi', ['lembaga' => 'BAN-PT', 'tahun_terbit' => 2024])->assertStatus(403);
    }

    public function test_endpoint_baca_tetap_publik(): void
    {
        $this->getJson('/api/v1/kurikulum')->assertOk();
        $this->getJson('/api/v1/akreditasi')->assertOk();
    }

    // ---- kurikulum ----

    public function test_admin_dan_kaprodi_bisa_menambah_kurikulum(): void
    {
        $this->perluProdi();

        foreach ([1, 2] as $peran) {
            $this->masukSebagai($peran);
            $this->postJson('/api/v1/kurikulum', ['nama_kurikulum' => "Kurikulum peran {$peran}", 'tahun_terbit' => 2026])
                ->assertStatus(201)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.nama_kurikulum', "Kurikulum peran {$peran}")
                ->assertJsonPath('data.status', true);
            $this->assertDatabaseHas('kurikulum', ['nama_kurikulum' => "Kurikulum peran {$peran}"]);
        }
    }

    public function test_tambah_kurikulum_dengan_pdf_menyimpan_berkas(): void
    {
        $this->perluProdi();
        $this->masukSebagai(1);

        $r = $this->post('/api/v1/kurikulum', [
            'nama_kurikulum' => 'Kurikulum PDF', 'tahun_terbit' => 2026,
            'pdf' => UploadedFile::fake()->create('k.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $link = Kurikulum::query()->where('nama_kurikulum', 'Kurikulum PDF')->value('link');
        $this->assertNotEmpty($link);
        Storage::disk('public')->assertExists($link);
        $this->assertNotNull($r->json('data.link'));
    }

    public function test_tambah_kurikulum_tidak_valid_422(): void
    {
        $this->masukSebagai(1);

        $this->postJson('/api/v1/kurikulum', [])->assertStatus(422)->assertJsonPath('success', false);
        $this->postJson('/api/v1/kurikulum', ['nama_kurikulum' => 'X', 'tahun_terbit' => 1800])->assertStatus(422);
    }

    public function test_ubah_balik_status_dan_hapus_kurikulum(): void
    {
        $this->masukSebagai(2);
        $k = $this->buatKurikulum('Lama');
        $this->buatMataKuliahLangsung($k->id_kurikulum);

        $this->putJson("/api/v1/kurikulum/{$k->id_kurikulum}", ['nama_kurikulum' => 'Baru', 'tahun_terbit' => 2027])
            ->assertOk()->assertJsonPath('data.nama_kurikulum', 'Baru');

        $this->patchJson("/api/v1/kurikulum/{$k->id_kurikulum}/status")->assertOk()->assertJsonPath('data.status', false);
        $this->patchJson("/api/v1/kurikulum/{$k->id_kurikulum}/status")->assertOk()->assertJsonPath('data.status', true);

        $this->deleteJson("/api/v1/kurikulum/{$k->id_kurikulum}")->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('kurikulum', ['id_kurikulum' => $k->id_kurikulum]);
        $this->assertDatabaseMissing('mata_kuliah', ['id_kurikulum' => $k->id_kurikulum]);
    }

    public function test_kurikulum_tidak_ada_404(): void
    {
        $this->masukSebagai(1);

        $this->putJson('/api/v1/kurikulum/999999', ['nama_kurikulum' => 'X', 'tahun_terbit' => 2026])->assertStatus(404);
        $this->patchJson('/api/v1/kurikulum/999999/status')->assertStatus(404);
        $this->deleteJson('/api/v1/kurikulum/999999')->assertStatus(404);
    }

    private function buatMataKuliahLangsung(int $idKurikulum): MataKuliah
    {
        return MataKuliah::query()->create(['id_kurikulum' => $idKurikulum] + $this->dataMk());
    }

    // ---- mata kuliah ----

    public function test_tambah_ubah_hapus_mata_kuliah(): void
    {
        $this->masukSebagai(1);
        $k = $this->buatKurikulum();

        $id = $this->postJson("/api/v1/kurikulum/{$k->id_kurikulum}/mata-kuliah", $this->dataMk())
            ->assertStatus(201)
            ->assertJsonPath('data.kode_mk', 'TRK101')
            ->assertJsonPath('data.id_kurikulum', $k->id_kurikulum)
            ->json('data.id_mk');

        $this->putJson("/api/v1/mata-kuliah/{$id}", $this->dataMk(['nama_mk' => 'Pemrograman Lanjut', 'semester' => 2]))
            ->assertOk()
            ->assertJsonPath('data.nama_mk', 'Pemrograman Lanjut')
            ->assertJsonPath('data.semester', 2);

        $this->deleteJson("/api/v1/mata-kuliah/{$id}")->assertOk();
        $this->assertDatabaseMissing('mata_kuliah', ['id_mk' => $id]);
    }

    public function test_kode_mata_kuliah_harus_unik_per_kurikulum(): void
    {
        $this->masukSebagai(1);
        $k1 = $this->buatKurikulum('A');
        $k2 = $this->buatKurikulum('B');

        $this->postJson("/api/v1/kurikulum/{$k1->id_kurikulum}/mata-kuliah", $this->dataMk())->assertStatus(201);
        $this->postJson("/api/v1/kurikulum/{$k1->id_kurikulum}/mata-kuliah", $this->dataMk())->assertStatus(422);
        // kode yang sama boleh dipakai di kurikulum lain
        $this->postJson("/api/v1/kurikulum/{$k2->id_kurikulum}/mata-kuliah", $this->dataMk())->assertStatus(201);
    }

    public function test_ubah_mata_kuliah_dengan_kode_sendiri_tidak_dianggap_duplikat(): void
    {
        $this->masukSebagai(2);
        $k = $this->buatKurikulum();
        $m = $this->buatMataKuliahLangsung($k->id_kurikulum);

        $this->putJson("/api/v1/mata-kuliah/{$m->id_mk}", $this->dataMk(['sks_teori' => 3]))->assertOk();
    }

    public function test_mata_kuliah_tidak_valid_dan_tidak_ada(): void
    {
        $this->masukSebagai(1);
        $k = $this->buatKurikulum();

        $this->postJson("/api/v1/kurikulum/{$k->id_kurikulum}/mata-kuliah", $this->dataMk(['semester' => 9]))->assertStatus(422);
        $this->postJson("/api/v1/kurikulum/{$k->id_kurikulum}/mata-kuliah", [])->assertStatus(422);
        $this->postJson('/api/v1/kurikulum/999999/mata-kuliah', $this->dataMk())->assertStatus(404);
        $this->putJson('/api/v1/mata-kuliah/999999', $this->dataMk())->assertStatus(404);
        $this->deleteJson('/api/v1/mata-kuliah/999999')->assertStatus(404);
    }

    // ---- akreditasi ----

    public function test_admin_menambah_akreditasi_dan_mengaktifkan_langsung(): void
    {
        $this->masukSebagai(1);
        $lama = $this->buatAkreditasi('Lama', true);

        $this->postJson('/api/v1/akreditasi', ['lembaga' => 'BAN-PT', 'tahun_terbit' => 2024, 'aktifkan' => true])
            ->assertStatus(201)
            ->assertJsonPath('data.lembaga', 'BAN-PT')
            ->assertJsonPath('data.status', true);

        $this->assertSame(1, Akreditasi::query()->where('status', true)->count());
        $this->assertFalse((bool) $lama->refresh()->status);
    }

    public function test_aktifkan_hanya_satu_akreditasi(): void
    {
        $this->masukSebagai(1);
        $a = $this->buatAkreditasi('A', true);
        $b = $this->buatAkreditasi('B', false);

        $this->postJson("/api/v1/akreditasi/{$b->id_akreditasi}/aktifkan")->assertOk()->assertJsonPath('data.status', true);

        $this->assertFalse((bool) $a->refresh()->status);
        $this->getJson('/api/v1/akreditasi/aktif')->assertOk()->assertJsonPath('data.lembaga', 'B');
    }

    public function test_ubah_akreditasi_dan_validasi(): void
    {
        $this->masukSebagai(1);
        $a = $this->buatAkreditasi('A', false);

        $this->putJson("/api/v1/akreditasi/{$a->id_akreditasi}", ['lembaga' => 'LAM Infokom', 'tahun_terbit' => 2025])
            ->assertOk()->assertJsonPath('data.lembaga', 'LAM Infokom');
        $this->putJson("/api/v1/akreditasi/{$a->id_akreditasi}", ['tahun_terbit' => 2025])->assertStatus(422);
        $this->postJson('/api/v1/akreditasi', [])->assertStatus(422);
    }

    public function test_hapus_akreditasi_aktif_409_nonaktif_berhasil(): void
    {
        $this->masukSebagai(1);
        $aktif = $this->buatAkreditasi('Aktif', true);
        $nonaktif = $this->buatAkreditasi('Nonaktif', false);

        $this->deleteJson("/api/v1/akreditasi/{$aktif->id_akreditasi}")->assertStatus(409);
        $this->deleteJson("/api/v1/akreditasi/{$nonaktif->id_akreditasi}")->assertOk();
        $this->assertDatabaseMissing('akreditasi', ['id_akreditasi' => $nonaktif->id_akreditasi]);
        $this->deleteJson('/api/v1/akreditasi/999999')->assertStatus(404);
    }
}
