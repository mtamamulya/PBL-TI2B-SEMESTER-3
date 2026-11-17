<?php

namespace Tests\Feature\Api;

use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Mahasiswa;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PengajuanKaprodiApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/kaprodi/pengajuan';

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

    private function sebagai(string $role = 'kaprodi'): static
    {
        return $this->actingAs($this->pengguna($role), 'sanctum');
    }

    private function buatPengajuan(string $status = 'direview', string $nama = 'Lomba Gemastik'): Kegiatan
    {
        self::$urut++;
        $nomor = str_pad((string) self::$urut, 4, '0', STR_PAD_LEFT);

        $jenis = JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Mahasiswa']);
        $kategori = Kategori::firstOrCreate(
            ['id_jenis_kegiatan' => $jenis->id_jenis_kegiatan, 'nama_kategori' => 'Lomba'],
            ['kebutuhan' => 'surat', 'isi_surat' => 'Surat tugas [NOMOR_SURAT]', 'lampiran_peserta' => false]
        );

        $mhs = $this->pengguna('mahasiswa');
        Mahasiswa::create(['id_user' => $mhs->id_user, 'nim' => '33424'.$nomor, 'nama' => 'Mahasiswa '.$nomor, 'tahun_masuk' => 2024]);

        $k = Kegiatan::create([
            'id_kategori' => $kategori->id_kategori,
            'id_user' => $mhs->id_user,
            'nama_kegiatan' => $nama,
            'nomor_sk' => $status !== 'diajukan' ? '010/SK/2026' : null,
            'tanggal_sk' => $status !== 'diajukan' ? '2026-11-16' : null,
        ]);

        if ($status !== 'diajukan') {
            Timeline::create([
                'id_kegiatan' => $k->id_kegiatan,
                'status' => 'direview',
                'tanggal_status' => now()->subMinutes(2),
            ]);
        }

        if ($status !== 'direview') {
            Timeline::create([
                'id_kegiatan' => $k->id_kegiatan,
                'status' => $status,
                'tanggal_status' => now()->subMinute(),
            ]);
        }

        return $k;
    }

    public function test_api_requires_kaprodi_role(): void
    {
        $k = $this->buatPengajuan('direview');

        $this->getJson(self::API)->assertUnauthorized();
        $this->sebagai('admin')->getJson(self::API)->assertForbidden();
        $this->sebagai('mahasiswa')->getJson(self::API.'/'.$k->id_kegiatan)->assertForbidden();
        $this->sebagai('admin')->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', ['aksi' => 'setujui'])->assertForbidden();
    }

    public function test_index_only_shows_submissions_sent_by_admin(): void
    {
        $this->buatPengajuan('diajukan', 'Belum Diperiksa Admin');
        $this->buatPengajuan('direview', 'Menunggu TTD Kaprodi');
        $this->buatPengajuan('diterima', 'Sudah Disetujui Kaprodi');

        $this->sebagai()->getJson(self::API)
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->sebagai()->getJson(self::API.'?status=menunggu')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.nama_kegiatan', 'Menunggu TTD Kaprodi');
    }

    public function test_kaprodi_can_approve_and_sign_submission(): void
    {
        $k = $this->buatPengajuan('direview', 'Kompetisi Nasional');

        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'setujui',
        ])->assertOk()
            ->assertJsonPath('data.status', 'diterima');

        $this->assertDatabaseHas('timeline', [
            'id_kegiatan' => $k->id_kegiatan,
            'status' => 'diterima',
        ]);

        // Tidak bisa disetujui ulang jika sudah diterima
        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'setujui',
        ])->assertStatus(409);
    }

    public function test_kaprodi_revision_returns_submission_to_admin_and_clears_sk(): void
    {
        $k = $this->buatPengajuan('direview', 'Workshop UI');

        $this->sebagai()->postJson(self::API.'/'.$k->id_kegiatan.'/keputusan', [
            'aksi' => 'revisi',
            'catatan_revisi' => 'Perbaiki tanggal pelaksanaan pada surat.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'direvisi')
            ->assertJsonPath('data.nomor_sk', null);

        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan' => $k->id_kegiatan,
            'nomor_sk' => null,
        ]);
    }
}
