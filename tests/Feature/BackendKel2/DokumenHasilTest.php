<?php

namespace Tests\Feature\BackendKel2;

use App\BackendKel2\Dokumen\JenisDokumenBaku;
use App\Models\AnggotaKegiatan;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Role;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DokumenHasilTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'mahasiswa'): User
    {
        return User::factory()->create(['id_role' => Role::query()->firstOrCreate(['nama_role' => $role])->getKey()]);
    }

    private function kegiatan(User $user, string $status = 'selesai'): Kegiatan
    {
        $jenis = JenisKegiatan::query()->firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Mahasiswa']);
        $kategori = Kategori::query()->firstOrCreate(['id_jenis_kegiatan' => $jenis->getKey(), 'nama_kategori' => 'Lomba']);
        $kegiatan = Kegiatan::create(['id_kategori' => $kategori->getKey(), 'id_user' => $user->getKey(), 'nama_kegiatan' => 'Kegiatan Dokumen']);
        Timeline::create(['id_kegiatan' => $kegiatan->getKey(), 'status' => $status, 'tanggal_status' => now()]);

        return $kegiatan;
    }

    private function dokumen(Kegiatan $kegiatan, string $jenis, bool $lampiran = false): Dokumen
    {
        $tipe = JenisDokumen::query()->firstOrCreate(['nama_jenis_dokumen' => $jenis]);

        return Dokumen::create([
            'id_kegiatan' => $kegiatan->getKey(), 'id_jenis_dokumen' => $tipe->getKey(),
            'nama_file' => $jenis, 'tipe_dokumen' => 'tautan',
            'link_dokumen' => 'https://drive.google.com/file/d/hasil/view', 'is_lampiran' => $lampiran,
        ]);
    }

    public function test_pemilik_mendapat_dokumen_hasil_setelah_selesai(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user);
        $this->dokumen($kegiatan, JenisDokumenBaku::SURAT_HASIL);
        $terbaru = $this->dokumen($kegiatan, JenisDokumenBaku::SURAT_HASIL);

        $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen-hasil");
        $response->assertOk()->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($terbaru->getKey(), $data[0]['id_dokumen']);
    }

    public function test_anggota_kegiatan_mendapat_dokumen_hasil(): void
    {
        $pemilik = $this->user();
        $anggota = $this->user('dosen');
        $kegiatan = $this->kegiatan($pemilik);
        AnggotaKegiatan::create(['id_kegiatan' => $kegiatan->getKey(), 'id_user' => $anggota->getKey(), 'peran' => 'anggota']);
        $this->dokumen($kegiatan, JenisDokumenBaku::SURAT_HASIL);

        $this->actingAs($anggota, 'sanctum')->getJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen-hasil")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_dokumen_hasil_kosong_saat_belum_ada(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user);

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen-hasil")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_dokumen_hasil_ditolak_jika_status_belum_selesai(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user, 'diajukan');

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen-hasil")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_dokumen_hasil_ditolak_untuk_bukan_pemilik(): void
    {
        $pemilik = $this->user();
        $lain = $this->user();
        $kegiatan = $this->kegiatan($pemilik);

        $this->actingAs($lain, 'sanctum')->getJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen-hasil")
            ->assertForbidden();
    }

    public function test_dokumen_hasil_ditolak_tanpa_token(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user);

        $this->getJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen-hasil")
            ->assertUnauthorized();
    }

    public function test_pengguna_dapat_menghapus_dokumen_pendukung(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user, 'diajukan');
        $dokumen = $this->dokumen($kegiatan, 'Proposal');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen/{$dokumen->getKey()}")
            ->assertOk()
            ->assertJsonPath('success', true);
        $this->assertDatabaseMissing('dokumen', ['id_dokumen' => $dokumen->getKey()]);
    }

    public function test_pengguna_dapat_menghapus_dokumen_saat_direvisi(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user, 'direvisi');
        $dokumen = $this->dokumen($kegiatan, 'Proposal');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen/{$dokumen->getKey()}")
            ->assertOk();
    }

    public function test_hapus_ditolak_untuk_dokumen_surat_hasil(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user, 'diajukan');
        $hasil = $this->dokumen($kegiatan, JenisDokumenBaku::SURAT_HASIL);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen/{$hasil->getKey()}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_hapus_ditolak_untuk_dokumen_lampiran(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user, 'diajukan');
        $lampiran = $this->dokumen($kegiatan, 'Proposal', true);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen/{$lampiran->getKey()}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_hapus_ditolak_saat_status_sudah_diterima(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user, 'diterima');
        $dokumen = $this->dokumen($kegiatan, 'Proposal');

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen/{$dokumen->getKey()}")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_hapus_ditolak_untuk_bukan_pemilik(): void
    {
        $pemilik = $this->user();
        $lain = $this->user();
        $kegiatan = $this->kegiatan($pemilik, 'diajukan');
        $dokumen = $this->dokumen($kegiatan, 'Proposal');

        $this->actingAs($lain, 'sanctum')->deleteJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen/{$dokumen->getKey()}")
            ->assertForbidden();
    }

    public function test_hapus_ditolak_tanpa_token(): void
    {
        $user = $this->user();
        $kegiatan = $this->kegiatan($user, 'diajukan');
        $dokumen = $this->dokumen($kegiatan, 'Proposal');

        $this->deleteJson("/api/v1/kegiatan/{$kegiatan->getKey()}/dokumen/{$dokumen->getKey()}")
            ->assertUnauthorized();
    }
}
