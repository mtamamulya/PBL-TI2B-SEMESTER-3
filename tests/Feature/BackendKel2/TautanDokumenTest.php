<?php

namespace Tests\Feature\BackendKel2;

use App\Models\AnggotaKegiatan;
use App\Models\DetailKegiatan;
use App\Models\Dokumen;
use App\Models\IsianKategori;
use App\Models\JenisDokumen;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TautanDokumenTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(string $role): User
    {
        return User::factory()->create([
            'id_role' => Role::query()->firstOrCreate(['nama_role' => $role])->getKey(),
        ]);
    }

    private function kegiatan(User $pemilik): Kegiatan
    {
        $jenis = JenisKegiatan::query()->firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Mahasiswa']);
        $kategori = Kategori::query()->firstOrCreate([
            'id_jenis_kegiatan' => $jenis->getKey(),
            'nama_kategori' => 'Lomba',
        ]);

        return Kegiatan::create(['id_kategori' => $kategori->getKey(), 'id_user' => $pemilik->getKey(), 'nama_kegiatan' => 'Kompetisi Aplikasi']);
    }

    private function jenisDokumen(): JenisDokumen
    {
        return JenisDokumen::query()->firstOrCreate(['nama_jenis_dokumen' => 'Proposal']);
    }

    private function payload(array $tambahan = []): array
    {
        return array_merge([
            'id_jenis_dokumen' => $this->jenisDokumen()->getKey(),
            'nama_file' => 'Proposal kegiatan',
            'tautan' => 'https://drive.google.com/file/d/abc123/view?usp=sharing',
        ], $tambahan);
    }

    public function test_mahasiswa_pemilik_dapat_menyimpan_tautan_drive_yang_dinormalisasi(): void
    {
        $mahasiswa = $this->pengguna('mahasiswa');
        $kegiatan = $this->kegiatan($mahasiswa);

        $this->actingAs($mahasiswa, 'sanctum')
            ->postJson('/api/v1/kegiatan/'.$kegiatan->getKey().'/dokumen', $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.link_dokumen', 'https://drive.google.com/file/d/abc123/preview')
            ->assertJsonPath('data.link_preview', 'https://drive.google.com/file/d/abc123/preview')
            ->assertJsonPath('data.is_lampiran', false);

        $this->assertDatabaseHas('dokumen', ['id_kegiatan' => $kegiatan->getKey(), 'link_dokumen' => 'https://drive.google.com/file/d/abc123/preview', 'is_lampiran' => false]);
    }

    public function test_dosen_anggota_dapat_menyimpan_tautan_sharepoint(): void
    {
        $pemilik = $this->pengguna('mahasiswa');
        $dosen = $this->pengguna('dosen');
        $kegiatan = $this->kegiatan($pemilik);
        AnggotaKegiatan::create(['id_kegiatan' => $kegiatan->getKey(), 'id_user' => $dosen->getKey(), 'peran' => 'anggota']);

        $this->actingAs($dosen, 'sanctum')
            ->postJson('/api/v1/kegiatan/'.$kegiatan->getKey().'/dokumen', $this->payload([
                'tautan' => 'https://polines.sharepoint.com/:w:/r/sites/kemahasiswaan/dokumen',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.link_dokumen', 'https://polines.sharepoint.com/:w:/r/sites/kemahasiswaan/dokumen');
    }

    public function test_menolak_skema_host_dan_userinfo_yang_tidak_valid(): void
    {
        $mahasiswa = $this->pengguna('mahasiswa');
        $kegiatan = $this->kegiatan($mahasiswa);

        foreach (['http://drive.google.com/file/d/abc123/view', 'https://drive.google.com.evil.test/file/d/abc123/view', 'https://user:rahasia@drive.google.com/file/d/abc123/view'] as $tautan) {
            $this->actingAs($mahasiswa, 'sanctum')
                ->postJson('/api/v1/kegiatan/'.$kegiatan->getKey().'/dokumen', $this->payload(['tautan' => $tautan]))
                ->assertUnprocessable()
                ->assertJsonPath('success', false)
                ->assertJsonStructure(['errors' => ['tautan']]);
        }
    }

    public function test_pengguna_lain_tidak_dapat_menambah_atau_melihat_dokumen_kegiatan(): void
    {
        $pemilik = $this->pengguna('mahasiswa');
        $lain = $this->pengguna('mahasiswa');
        $kegiatan = $this->kegiatan($pemilik);
        $dokumen = Dokumen::create([
            'id_jenis_dokumen' => $this->jenisDokumen()->getKey(), 'id_kegiatan' => $kegiatan->getKey(),
            'nama_file' => 'Proposal', 'tipe_dokumen' => 'tautan', 'link_dokumen' => 'https://drive.google.com/file/d/abc123/preview',
        ]);

        $this->actingAs($lain, 'sanctum')->postJson('/api/v1/kegiatan/'.$kegiatan->getKey().'/dokumen', $this->payload())->assertForbidden();
        $this->actingAs($lain, 'sanctum')->getJson('/api/v1/kegiatan/'.$kegiatan->getKey().'/dokumen/'.$dokumen->getKey())->assertForbidden();
    }

    public function test_admin_hanya_dapat_melihat_dokumen(): void
    {
        $pemilik = $this->pengguna('mahasiswa');
        $admin = $this->pengguna('admin');
        $kegiatan = $this->kegiatan($pemilik);
        $dokumen = Dokumen::create([
            'id_jenis_dokumen' => $this->jenisDokumen()->getKey(), 'id_kegiatan' => $kegiatan->getKey(),
            'nama_file' => 'Proposal', 'tipe_dokumen' => 'tautan', 'link_dokumen' => 'https://docs.google.com/document/d/abc123/edit',
        ]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/kegiatan/'.$kegiatan->getKey().'/dokumen/'.$dokumen->getKey())->assertOk()->assertJsonPath('success', true);
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/kegiatan/'.$kegiatan->getKey().'/dokumen', $this->payload())->assertForbidden();
    }

    public function test_detail_kegiatan_harus_berasal_dari_kegiatan_yang_sama(): void
    {
        $mahasiswa = $this->pengguna('mahasiswa');
        $kegiatan = $this->kegiatan($mahasiswa);
        $kegiatanLain = $this->kegiatan($mahasiswa);
        $isian = IsianKategori::query()->firstOrCreate(['id_kategori' => $kegiatanLain->id_kategori, 'input' => 'Lokasi'], ['jenis_input' => 'teks', 'aturan' => 'NOT NULL', 'jumlah_isian' => 1]);
        $detailLain = DetailKegiatan::create(['id_kegiatan' => $kegiatanLain->getKey(), 'id_isian_kategori' => $isian->getKey(), 'isian' => 'Semarang']);

        $this->actingAs($mahasiswa, 'sanctum')
            ->postJson('/api/v1/kegiatan/'.$kegiatan->getKey().'/dokumen', $this->payload(['id_detail_kegiatan' => $detailLain->getKey()]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['id_detail_kegiatan']]);
    }
}
