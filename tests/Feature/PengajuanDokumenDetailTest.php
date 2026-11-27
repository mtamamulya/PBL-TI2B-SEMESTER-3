<?php

namespace Tests\Feature;

use App\BackendKel2\Dokumen\JenisDokumenBaku;
use App\Http\Middleware\MahasiswaOnly;
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

class PengajuanDokumenDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(MahasiswaOnly::class);
    }

    private function kegiatan(string $status): array
    {
        $user = User::factory()->create([
            'id_role' => Role::query()->firstOrCreate(['id_role' => 4], ['nama_role' => 'mahasiswa'])->getKey(),
        ]);
        $jenis = JenisKegiatan::query()->firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Mahasiswa']);
        $kategori = Kategori::query()->firstOrCreate([
            'id_jenis_kegiatan' => $jenis->getKey(),
            'nama_kategori' => 'Lomba',
        ]);
        $kegiatan = Kegiatan::create([
            'id_kategori' => $kategori->getKey(),
            'id_user' => $user->getKey(),
            'nama_kegiatan' => 'Kompetisi Dokumen',
        ]);
        Timeline::create(['id_kegiatan' => $kegiatan->getKey(), 'status' => $status, 'tanggal_status' => now()]);

        return [$user, $kegiatan];
    }

    private function dokumen(Kegiatan $kegiatan, string $jenis, string $nama): Dokumen
    {
        $jenisDokumen = JenisDokumen::query()->firstOrCreate(['nama_jenis_dokumen' => $jenis]);

        return Dokumen::create([
            'id_kegiatan' => $kegiatan->getKey(),
            'id_jenis_dokumen' => $jenisDokumen->getKey(),
            'nama_file' => $nama,
            'tipe_dokumen' => 'tautan',
            'link_dokumen' => 'https://drive.google.com/file/d/'.$nama.'/view',
        ]);
    }

    public function test_detail_menampilkan_lampiran_dan_dokumen_hasil_terbaru_saat_selesai(): void
    {
        [$user, $kegiatan] = $this->kegiatan('selesai');
        $this->dokumen($kegiatan, 'Proposal', 'proposal.pdf');
        $this->dokumen($kegiatan, JenisDokumenBaku::SURAT_HASIL, 'hasil-lama.pdf');
        $this->dokumen($kegiatan, JenisDokumenBaku::SURAT_HASIL, 'hasil-terbaru.pdf');

        $this->actingAs($user)
            ->get(route('pengajuan.show', $kegiatan))
            ->assertOk()
            ->assertSee('proposal.pdf')
            ->assertSee('hasil-terbaru.pdf')
            ->assertDontSee('hasil-lama.pdf')
            ->assertDontSee('Dokumen_hasil_pengajuan.pdf');
    }

    public function test_detail_aman_dan_menampilkan_hasil_belum_tersedia_sebelum_selesai(): void
    {
        [$user, $kegiatan] = $this->kegiatan('diajukan');
        $this->dokumen($kegiatan, 'Proposal', 'proposal.pdf');
        $this->dokumen($kegiatan, JenisDokumenBaku::SURAT_HASIL, 'hasil-belum-boleh.pdf');

        $this->actingAs($user)
            ->get(route('pengajuan.show', $kegiatan))
            ->assertOk()
            ->assertSee('proposal.pdf')
            ->assertDontSee('hasil-belum-boleh.pdf')
            ->assertSee('Dokumen hasil belum tersedia.');
    }
}
