<?php

namespace Tests\Feature\BackendKel3;

use App\Models\AnggotaKegiatan;
use App\Models\Berita;
use App\Models\DetailKegiatan;
use App\Models\Dosen;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegrasiKelompok3Test extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private int $category;

    private int $field;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $role = DB::table('roles')->insertGetId(['nama_role' => 'dosen'], 'id_role');
        $this->owner = User::create(['id_role' => $role, 'email' => 'owner@test.local', 'password' => 'password', 'status' => 1]);
        $this->other = User::create(['id_role' => $role, 'email' => 'other@test.local', 'password' => 'password', 'status' => 1]);
        Dosen::create(['id_user' => $this->owner->getKey(), 'nip' => '123456789', 'nama' => 'Pemilik']);
        $jenis = DB::table('jenis_kegiatan')->insertGetId(['nama_jenis_kegiatan' => 'Kegiatan Dosen'], 'id_jenis_kegiatan');
        $this->category = DB::table('kategori')->insertGetId(['id_jenis_kegiatan' => $jenis, 'nama_kategori' => 'Penelitian', 'isi_surat' => 'Surat untuk kegiatan'], 'id_kategori');
        $this->field = DB::table('isian_kategori')->insertGetId(['id_kategori' => $this->category, 'input' => 'tanggal_mulai', 'jenis_input' => 'tanggal', 'jumlah_isian' => 1], 'id_isian_kategori');
    }

    private function activity(?User $user = null): Kegiatan
    {
        return Kegiatan::create(['id_user' => ($user ?? $this->owner)->getKey(), 'id_kategori' => $this->category, 'nama_kegiatan' => 'Riset integrasi']);
    }

    private function tambahStatus(Kegiatan $k, string $status, int $minutes = 0): void
    {
        Timeline::create(['id_kegiatan' => $k->getKey(), 'status' => $status, 'tanggal_status' => now()->addMinutes($minutes)]);
    }

    public function test_semua_modul_baru_memerlukan_dosen_aktif(): void
    {
        foreach (['penelitian', 'pengabdian', 'dashboard', 'notifikasi', 'berita', 'pengajuan/1/dokumen'] as $path) {
            $this->getJson('/api/v1/dosen/'.$path)->assertUnauthorized();
        }
        $this->other->update(['status' => false]);
        $this->actingAs($this->other, 'sanctum')->getJson('/api/v1/dosen/dashboard')->assertForbidden();
    }

    public function test_penelitian_menyimpan_tanggal_dinamis_dan_mendukung_detail_update_delete(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $id = $this->postJson('/api/v1/dosen/penelitian', [
            'id_kategori' => $this->category, 'nama_kegiatan' => 'Riset baru', 'tanggal_mulai' => '2026-11-20',
        ])->assertCreated()->json('data.id_kegiatan');
        $this->assertDatabaseHas('kegiatan', ['id_kegiatan' => $id, 'id_user' => $this->owner->getKey()]);
        $this->assertDatabaseHas('detail_kegiatan', ['id_kegiatan' => $id, 'id_isian_kategori' => $this->field, 'isian' => '2026-11-20']);
        $this->getJson('/api/v1/dosen/penelitian/'.$id)->assertOk()->assertJsonPath('data.tanggal_mulai', '2026-11-20');
        $this->patchJson('/api/v1/dosen/penelitian/'.$id, ['nama_kegiatan' => 'Riset diubah', 'tanggal_mulai' => '2026-11-21'])->assertOk();
        $this->getJson('/api/v1/dosen/penelitian?per_page=0')->assertOk()->assertJsonPath('data.0.tanggal_mulai', '2026-11-21');
        $this->deleteJson('/api/v1/dosen/penelitian/'.$id)->assertOk();
        $this->assertDatabaseMissing('kegiatan', ['id_kegiatan' => $id]);
    }

    public function test_penelitian_menolak_kategori_pengabdian_dan_akses_silang(): void
    {
        $k = $this->activity();
        $this->actingAs($this->other, 'sanctum');
        foreach (['get', 'patch', 'delete'] as $method) {
            $this->{$method.'Json'}('/api/v1/dosen/penelitian/'.$k->getKey(), [])->assertNotFound();
        }
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/dosen/pengabdian', [
            'id_kategori' => $this->category, 'nama_kegiatan' => 'Salah modul',
        ])->assertUnprocessable();
        $this->getJson('/api/v1/dosen/pengabdian/'.$k->getKey())->assertNotFound();
    }

    public function test_payload_form_lama_memakai_kategori_dan_lampiran_yang_sama(): void
    {
        $id = $this->actingAs($this->owner, 'sanctum')->postJson('/api/v1/dosen/penelitian', [
            'kategori' => 'Penelitian', 'nama_kegiatan' => 'Form lama',
            'detail' => ['tanggal_mulai' => '2026-11-20'],
            'dokumen' => [['nama' => 'Proposal', 'jenis' => 'Proposal', 'tautan' => 'https://example.test/proposal.pdf']],
        ])->assertCreated()->json('data.id_kegiatan');
        $this->assertDatabaseHas('dokumen', ['id_kegiatan' => $id, 'nama_file' => 'Proposal']);
    }

    public function test_anggota_bisa_membaca_tetapi_tidak_menghapus_penelitian_pemilik(): void
    {
        $k = $this->activity();
        AnggotaKegiatan::create(['id_kegiatan' => $k->getKey(), 'id_user' => $this->other->getKey(), 'peran' => 'anggota']);
        $this->actingAs($this->other, 'sanctum')->getJson('/api/v1/dosen/penelitian/'.$k->getKey())->assertOk();
        $this->patchJson('/api/v1/dosen/penelitian/'.$k->getKey(), ['nama_kegiatan' => 'Tidak boleh'])->assertForbidden();
        $this->deleteJson('/api/v1/dosen/penelitian/'.$k->getKey())->assertForbidden();
        $this->tambahStatus($k, 'diterima');
        $this->actingAs($this->owner, 'sanctum')->patchJson('/api/v1/dosen/penelitian/'.$k->getKey(), ['nama_kegiatan' => 'Tidak boleh'])->assertForbidden();
        $this->deleteJson('/api/v1/dosen/penelitian/'.$k->getKey())->assertForbidden();
    }

    public function test_lampiran_terhubung_ke_pengajuan_dan_dapat_diajukan_ulang(): void
    {
        $k = $this->activity();
        $url = '/api/v1/dosen/pengajuan/'.$k->getKey().'/dokumen';
        $this->actingAs($this->owner, 'sanctum');
        $id = $this->postJson($url, ['jenis_dokumen' => 'Proposal', 'files' => [UploadedFile::fake()->create('proposal.pdf', 20, 'application/pdf')]])
            ->assertCreated()->json('data.0.id_dokumen');
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.id_kegiatan', $k->getKey());
        $this->assertDatabaseHas('dokumen', ['id_dokumen' => $id, 'id_kegiatan' => $k->getKey()]);
        $this->postJson('/api/v1/dosen/pengajuan/'.$k->getKey().'/ajukan')->assertCreated();
        $this->deleteJson($url.'/'.$id)->assertForbidden();
        $this->tambahStatus($k, 'direvisi', 1);
        $this->travel(2)->minutes();
        $this->postJson($url, ['nama_file' => 'Revisi', 'file_path' => 'https://example.test/revisi.pdf', 'ajukan_kembali' => true])->assertCreated();
        $this->assertDatabaseCount('timeline', 3);
        $this->actingAs($this->other, 'sanctum')->getJson($url)->assertNotFound();
        $this->postJson($url, ['file_path' => 'https://example.test/wrong'])->assertNotFound();
        $this->deleteJson($url.'/'.$id)->assertNotFound();
    }

    public function test_dokumen_menolak_detail_kegiatan_lain_dan_menghapus_lampiran_draft(): void
    {
        $k = $this->activity();
        $other = $this->activity($this->other);
        $detail = DetailKegiatan::create(['id_kegiatan' => $other->getKey(), 'id_isian_kategori' => $this->field, 'isian' => '2026-11-22']);
        $url = '/api/v1/dosen/kegiatan/'.$k->getKey().'/dokumen';
        $this->actingAs($this->owner, 'sanctum')->postJson($url, ['file_path' => 'https://example.test/test', 'id_detail_kegiatan' => $detail->getKey()])->assertUnprocessable();
        $this->assertDatabaseCount('dokumen', 0);
        $id = $this->postJson($url, ['file_path' => 'https://example.test/test'])->assertCreated()->json('data.0.id_dokumen');
        $this->deleteJson($url.'/'.$id)->assertOk();
        $this->assertDatabaseCount('dokumen', 0);
        $this->postJson($url, ['file_path' => 'javascript:alert(1)'])->assertUnprocessable();
    }

    public function test_surat_belum_terbit_dan_kepemilikan_diperiksa(): void
    {
        $k = $this->activity();
        $url = '/api/v1/dosen/pengajuan/'.$k->getKey().'/surat';
        $this->actingAs($this->owner, 'sanctum')->getJson($url)->assertNotFound();
        $k->update(['nomor_sk' => 'SK/2026/1', 'tanggal_sk' => '2026-11-22']);
        $this->tambahStatus($k, 'diterima');
        $this->getJson($url)->assertOk()->assertJsonPath('data.nomor_sk', 'SK/2026/1');
        $this->actingAs($this->other, 'sanctum')->getJson($url)->assertNotFound();
    }

    public function test_dashboard_membaca_status_terakhir_dan_notifikasi_terbatas_pemilik(): void
    {
        $k = $this->activity();
        $this->tambahStatus($k, 'diajukan');
        $this->tambahStatus($k, 'diterima', 1);
        $this->tambahStatus($this->activity($this->other), 'direvisi', 2);
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/dosen/dashboard')->assertOk()
            ->assertJsonPath('data.ringkasan.total_kegiatan', 1)->assertJsonPath('data.ringkasan.pengajuan_menunggu', 0);
        $this->getJson('/api/v1/dosen/notifikasi')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.status', 'diterima');
        $this->get('/dosen/dashboard')->assertOk();
    }

    public function test_berita_draft_dapat_diubah_dan_diajukan_tanpa_menerbitkan_sendiri(): void
    {
        $this->actingAs($this->owner, 'sanctum');
        $input = ['judul_berita' => 'Berita riset', 'isi_berita' => 'Hasil kegiatan'];
        $id = $this->postJson('/api/v1/dosen/berita', $input)->assertCreated()->json('data.id_berita');
        $url = '/api/v1/dosen/berita/'.$id;
        $this->patchJson($url, ['judul_berita' => 'Revisi judul'])->assertOk()->assertJsonPath('data.judul_berita', 'Revisi judul');
        $this->patchJson($url, ['status' => 'publish'])->assertUnprocessable();
        $this->actingAs($this->other, 'sanctum')->getJson($url)->assertNotFound();
        $this->deleteJson($url)->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->patchJson($url, ['status' => 'diajukan'])->assertOk();
        $this->patchJson($url, ['judul_berita' => 'Ubah sesudah kirim'])->assertForbidden();
        $this->assertNull(Berita::findOrFail($id)->tanggal_publish);
    }
}
