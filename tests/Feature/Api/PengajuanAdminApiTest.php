<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKegiatan;
use App\Models\DetailKegiatan;
use App\Models\Dokumen;
use App\Models\Dosen;
use App\Models\IsianKategori;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Mahasiswa;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PengajuanAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/admin/pengajuan';

    private static int $urut = 0;

    private function pengguna(string $role): User
    {
        $id = DB::table('roles')->where('nama_role', $role)->value('id_role')
            ?? DB::table('roles')->insertGetId(['nama_role' => $role], 'id_role');

        return User::create([
            'id_role' => $id,
            'email' => uniqid().'@test.local',
            'password' => 'password',
        ]);
    }

    private function sebagai(string $role = 'admin'): static
    {
        return $this->actingAs($this->pengguna($role), 'sanctum');
    }

    private function jenisDokumenId(): int
    {
        $id = DB::table('jenis_dokumen')->value('id_jenis_dokumen');
        if ($id) {
            return (int) $id;
        }

        $kolom = array_diff(
            Schema::getColumnListing('jenis_dokumen'),
            ['id_jenis_dokumen', 'created_at', 'updated_at']
        );

        $data = [];
        foreach ($kolom as $k) {
            $data[$k] = 'Dokumen Pendukung';
        }

        return (int) DB::table('jenis_dokumen')->insertGetId($data, 'id_jenis_dokumen');
    }

    private function buatDokumen(Kegiatan $k, string $namaFile, string $tipe = 'proposal'): Dokumen
    {
        return Dokumen::create([
            'id_jenis_dokumen' => $this->jenisDokumenId(),
            'id_kegiatan' => $k->id_kegiatan,
            'nama_file' => $namaFile,
            'tipe_dokumen' => $tipe,
            'link_dokumen' => 'dokumen/'.$namaFile,
            'is_lampiran' => false,
        ]);
    }

    private function buatPengajuan(
        string $status = 'diajukan',
        string $nama = 'Lomba Gemastik',
        string $jenisNama = 'Kegiatan Mahasiswa',
        ?string $namaPengaju = null
    ): Kegiatan {
        self::$urut++;
        $nomor = str_pad((string) self::$urut, 4, '0', STR_PAD_LEFT);

        $jenis = JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => $jenisNama]);
        $kategori = Kategori::firstOrCreate(
            ['id_jenis_kegiatan' => $jenis->id_jenis_kegiatan, 'nama_kategori' => 'Lomba'],
            ['kebutuhan' => 'surat', 'isi_surat' => 'Surat tugas untuk [NAMA_PENGAJU]', 'lampiran_peserta' => true]
        );

        $user = $this->pengguna($jenisNama === 'Kegiatan Dosen' ? 'dosen' : 'mahasiswa');
        if ($jenisNama === 'Kegiatan Dosen') {
            Dosen::create([
                'id_user' => $user->id_user,
                'nip' => '19850101201001'.$nomor,
                'nama' => $namaPengaju ?? 'Dr. Budi Santoso '.$nomor,
            ]);
        } else {
            Mahasiswa::create([
                'id_user' => $user->id_user,
                'nim' => '33423'.$nomor,
                'nama' => $namaPengaju ?? 'Mahasiswa '.$nomor,
                'tahun_masuk' => 2023,
            ]);
        }

        $kegiatan = Kegiatan::create([
            'id_kategori' => $kategori->id_kategori,
            'id_user' => $user->id_user,
            'nama_kegiatan' => $nama,
        ]);

        $isianLokasi = IsianKategori::firstOrCreate(
            ['id_kategori' => $kategori->id_kategori, 'input' => 'Lokasi'],
            ['jenis_input' => 'teks', 'aturan' => 'NOT NULL', 'jumlah_isian' => 1]
        );
        DetailKegiatan::create([
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'id_isian_kategori' => $isianLokasi->id_isian_kategori,
            'isian' => 'Jakarta',
        ]);

        Timeline::create([
            'id_kegiatan' => $kegiatan->id_kegiatan,
            'status' => $status,
            'tanggal_status' => now()->subMinute(),
            'catatan_revisi' => $status === 'direvisi' ? 'Perbaiki proposal' : null,
        ]);

        return $kegiatan;
    }

    // ---------- akses ----------

    public function test_api_requires_login(): void
    {
        $this->getJson(self::API)->assertUnauthorized();
        $this->getJson(self::API.'/1')->assertUnauthorized();
        $this->postJson(self::API.'/1/keputusan', [])->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_pengajuan_admin(): void
    {
        $k = $this->buatPengajuan();

        $this->sebagai('mahasiswa')->getJson(self::API)->assertForbidden();
        $this->sebagai('dosen')->getJson(self::API.'/'.$k->id_kegiatan)->assertForbidden();
        $this->sebagai('kaprodi')->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'tolak',
            'catatan_revisi' => 'Tidak sesuai',
        ])->assertForbidden();
    }

    // ---------- baca daftar & detail ----------

    public function test_index_defaults_to_admin_queue_and_supports_filters(): void
    {
        $this->buatPengajuan('diajukan', 'Seminar Nasional AI', 'Kegiatan Mahasiswa', 'Dea Cantika');
        $this->buatPengajuan('direvisi', 'Workshop Cloud', 'Kegiatan Dosen', 'Dr. Budi Santoso');
        $this->buatPengajuan('direview', 'Sudah Direview Admin');
        $this->buatPengajuan('diterima', 'Sudah Disetujui Kaprodi');

        // Default: hanya diajukan & direvisi (antrean admin)
        $this->sebagai()->getJson(self::API)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);

        // Filter semua status
        $this->sebagai()->getJson(self::API.'?status=semua')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);

        // Filter jenis & pencarian nama
        $this->sebagai()->getJson(self::API.'?status=semua&jenis=Kegiatan%20Dosen')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.pengaju.nama', 'Dr. Budi Santoso');

        $this->sebagai()->getJson(self::API.'?q=TidakAda')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->sebagai()->getJson(self::API.'?q=Dea%20Cantika')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_show_returns_complete_submission_details(): void
    {
        $k = $this->buatPengajuan('diajukan', 'Kompetisi Robotik', 'Kegiatan Mahasiswa', 'Dea Cantika');
        $anggotaUser = $this->pengguna('mahasiswa');
        Mahasiswa::create([
            'id_user' => $anggotaUser->id_user,
            'nim' => '33423999',
            'nama' => 'Rizky Pratama',
            'tahun_masuk' => 2023,
        ]);

        AnggotaKegiatan::simpanDaftar($k, [
            ['id_user' => $k->id_user, 'peran' => 'ketua'],
            ['id_user' => $anggotaUser->id_user, 'peran' => 'anggota'],
        ]);

        $this->buatDokumen($k, 'proposal.pdf', 'proposal');

        $this->sebagai()->getJson(self::API.'/'.$k->id_kegiatan)
            ->assertOk()
            ->assertJsonPath('data.nama_kegiatan', 'Kompetisi Robotik')
            ->assertJsonPath('data.pengaju.nama', 'Dea Cantika')
            ->assertJsonCount(1, 'data.isian')
            ->assertJsonPath('data.isian.0.isian', 'Jakarta')
            ->assertJsonCount(2, 'data.anggota')
            ->assertJsonCount(1, 'data.dokumen')
            ->assertJsonCount(1, 'data.riwayat');

        $this->sebagai()->getJson(self::API.'/9999')->assertNotFound();
    }

    // ---------- keputusan admin ----------

    public function test_admin_can_approve_with_nomor_sk_and_selected_attachments(): void
    {
        $k = $this->buatPengajuan('diajukan', 'Lomba UI/UX');
        $dok1 = $this->buatDokumen($k, 'proposal.pdf', 'proposal');
        $dok2 = $this->buatDokumen($k, 'undangan.pdf', 'undangan');

        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'setujui',
            'nomor_sk' => '012/PL4.7.2/KM/2026',
            'tanggal_sk' => '2026-11-16',
            'lampiran' => [$dok1->id_dokumen],
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'direview')
            ->assertJsonPath('data.nomor_sk', '012/PL4.7.2/KM/2026')
            ->assertJsonPath('data.tanggal_sk', '2026-11-16');

        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan' => $k->id_kegiatan,
            'nomor_sk' => '012/PL4.7.2/KM/2026',
        ]);
        $this->assertDatabaseHas('timeline', [
            'id_kegiatan' => $k->id_kegiatan,
            'status' => 'direview',
        ]);
        $this->assertDatabaseHas('dokumen', ['id_dokumen' => $dok1->id_dokumen, 'is_lampiran' => 1]);
        $this->assertDatabaseHas('dokumen', ['id_dokumen' => $dok2->id_dokumen, 'is_lampiran' => 0]);
        $this->assertDatabaseHas('bukti_history', ['id_dokumen' => $dok1->id_dokumen]);
    }

    public function test_admin_can_request_revision_or_reject_with_notes(): void
    {
        $k = $this->buatPengajuan('diajukan', 'Pelatihan Jaringan');

        // Minta revisi
        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'revisi',
            'catatan_revisi' => 'Lengkapi rincian anggaran.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'direvisi')
            ->assertJsonPath('data.catatan_revisi', 'Lengkapi rincian anggaran.');

        // Tolak
        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'tolak',
            'catatan_revisi' => 'Kegiatan tidak relevan dengan prodi.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'ditolak')
            ->assertJsonPath('data.nomor_sk', null);

        $this->assertDatabaseCount('timeline', 3);
    }

    public function test_keputusan_validates_required_fields_and_foreign_attachments(): void
    {
        $k = $this->buatPengajuan('diajukan');
        $kLain = $this->buatPengajuan('diajukan', 'Kegiatan Lain');
        $dokLain = $this->buatDokumen($kLain, 'asing.pdf', 'lainnya');

        // Setujui tanpa nomor_sk -> 422
        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'setujui',
        ])->assertStatus(422)->assertJsonValidationErrors('nomor_sk');

        // Revisi tanpa catatan -> 422
        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'revisi',
        ])->assertStatus(422)->assertJsonValidationErrors('catatan_revisi');

        // Lampiran milik kegiatan lain -> 422
        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'setujui',
            'nomor_sk' => '099/SK/2026',
            'lampiran' => [$dokLain->id_dokumen],
        ])->assertStatus(422)->assertJsonValidationErrors('lampiran');
    }

    public function test_approved_by_kaprodi_submission_is_locked(): void
    {
        $k = $this->buatPengajuan('diterima', 'Sudah Sah');

        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'revisi',
            'catatan_revisi' => 'Coba ubah',
        ])->assertStatus(409);
    }
}
