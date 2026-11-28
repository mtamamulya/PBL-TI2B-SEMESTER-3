<?php

namespace Tests\Feature\BackendKel3;

use App\Models\DetailKegiatan;
use App\Models\Dosen;
use App\Models\IsianKategori;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlurRevisiPengajuanTest extends TestCase
{
    use RefreshDatabase;

    public function test_validasi_isian_wajib_per_kategori_dan_alur_lampiran_revisi_ajukan_ulang(): void
    {
        Storage::fake('public');

        $roleId = DB::table('roles')->insertGetId(['nama_role' => 'dosen'], 'id_role');
        $user = User::create([
            'id_role' => $roleId,
            'email' => 'dosen@test.local',
            'password' => 'password',
            'status' => 1,
        ]);
        Dosen::create([
            'id_user' => $user->getKey(),
            'nip' => '987654321',
            'nama' => 'Dosen Penguji',
        ]);

        $this->actingAs($user, 'sanctum');

        $jenisId = DB::table('jenis_kegiatan')->insertGetId([
            'nama_jenis_kegiatan' => 'Kegiatan Dosen',
        ], 'id_jenis_kegiatan');

        $kategori = Kategori::create([
            'id_jenis_kegiatan' => $jenisId,
            'nama_kategori' => 'Penelitian Terapan',
            'isi_surat' => 'Surat untuk kegiatan',
            'lampiran_peserta' => false,
        ]);

        $isianWajibTeks = IsianKategori::create([
            'id_kategori' => $kategori->id_kategori,
            'input' => 'sumber_dana',
            'jenis_input' => 'teks',
            'aturan' => 'NOT NULL',
            'jumlah_isian' => 1,
        ]);

        $kegiatan = Kegiatan::create([
            'id_kategori' => $kategori->id_kategori,
            'id_user' => $user->getKey(),
            'nama_kegiatan' => 'Riset AI Terapan',
        ]);

        // 1. Gagal diajukan jika isian wajib kategori ('sumber_dana') & dokumen masih kosong
        $this->postJson("/api/v1/dosen/pengajuan/{$kegiatan->id_kegiatan}/ajukan")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['isian.sumber_dana', 'dokumen']);

        // 2. Lengkapi isian wajib kategori & unggah dokumen awal
        DetailKegiatan::create([
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'id_isian_kategori' => $isianWajibTeks->id_isian_kategori,
            'isian' => 'Hibah Internal',
        ]);

        $this->postJson("/api/v1/dosen/pengajuan/{$kegiatan->id_kegiatan}/dokumen", [
            'jenis_dokumen' => 'Proposal',
            'files' => [UploadedFile::fake()->create('proposal_awal.pdf', 50, 'application/pdf')],
        ])->assertCreated();

        // 3. Berhasil diajukan pertama kali
        $this->postJson("/api/v1/dosen/pengajuan/{$kegiatan->id_kegiatan}/ajukan")
            ->assertCreated()
            ->assertJsonPath('data.status', 'diajukan');

        // 4. Reviewer mengembalikan dengan status 'direvisi'
        Timeline::create([
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'status' => 'direvisi',
            'tanggal_status' => now()->addMinute(),
            'catatan_revisi' => 'Mohon lampirkan surat tugas yang sudah bertanda tangan.',
        ]);

        // 5. Dosen mengunggah lampiran revisi baru
        $this->postJson("/api/v1/dosen/pengajuan/{$kegiatan->id_kegiatan}/dokumen", [
            'jenis_dokumen' => 'Surat Tugas',
            'files' => [UploadedFile::fake()->create('surat_tugas_revisi.pdf', 50, 'application/pdf')],
        ])->assertCreated();

        // 6. Dosen melakukan ajukan ulang dengan catatan perbaikan
        $this->postJson("/api/v1/dosen/pengajuan/{$kegiatan->id_kegiatan}/ajukan-ulang", [
            'catatan' => 'Surat tugas bertanda tangan sudah dilampirkan.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.catatan_revisi', 'Surat tugas bertanda tangan sudah dilampirkan.');

        // 7. Pastikan timeline mencatat seluruh urutan riwayat (diajukan -> direvisi -> diajukan)
        $this->getJson("/api/v1/dosen/pengajuan/{$kegiatan->id_kegiatan}/timeline")
            ->assertOk()
            ->assertJsonCount(3, 'data.riwayat_tahapan')
            ->assertJsonPath('data.status_terkini', 'diajukan');
    }
}
