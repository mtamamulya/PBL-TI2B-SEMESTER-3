<?php

namespace Tests\Feature\Api;

use App\Models\Prestasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** GET /admin/prestasi/rekap: sumber daftar kaprodi dan laporan PDF/Excel (admin dan kaprodi). */
class PrestasiRekapApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/admin/prestasi/rekap';

    private function pengguna(string $role): User
    {
        $roleIds = ['admin' => 1, 'kaprodi' => 2, 'dosen' => 3, 'mahasiswa' => 4];

        DB::table('roles')->updateOrInsert(
            ['id_role' => $roleIds[$role]],
            ['nama_role' => $role]
        );

        return User::create([
            'id_role' => $roleIds[$role],
            'email' => uniqid().'@test.local',
            'password' => 'password',
            'status' => true,
        ]);
    }

    private function sebagai(string $role = 'admin'): static
    {
        return $this->actingAs($this->pengguna($role), 'sanctum');
    }

    private function buatPrestasi(string $pemilikRole = 'mahasiswa', string $nama = 'Lomba Pemrograman', string $selesai = '2026-11-05'): Prestasi
    {
        $pemilik = $this->pengguna($pemilikRole);

        return Prestasi::create([
            'id_user' => $pemilik->id_user,
            'created_by' => $pemilik->id_user,
            'id_kegiatan' => null,
            'jenis_prestasi' => 'akademik',
            'bidang_lomba' => 'Teknologi Informasi',
            'nama_lomba' => $nama,
            'penyelenggara' => 'Politeknik',
            'lokasi' => 'Semarang',
            'capaian' => 'Juara 1',
            'tingkat_kejuaraan' => 'nasional',
            'tanggal_mulai' => '2026-04-01',
            'tanggal_selesai' => $selesai,
            'bentuk_partisipasi' => 'individu',
        ]);
    }

    public function test_rekap_mewajibkan_login(): void
    {
        $this->getJson(self::API)->assertUnauthorized();
    }

    public function test_dosen_dan_mahasiswa_dilarang(): void
    {
        $this->buatPrestasi();

        foreach (['mahasiswa', 'dosen'] as $role) {
            $this->sebagai($role)->getJson(self::API)->assertForbidden();
        }
    }

    public function test_admin_dan_kaprodi_dapat_melihat_rekap(): void
    {
        $this->buatPrestasi();

        foreach (['admin', 'kaprodi'] as $role) {
            $this->sebagai($role)
                ->getJson(self::API)
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.0.nama', 'Lomba Pemrograman')
                ->assertJsonPath('data.0.tipe', 'Mahasiswa')
                ->assertJsonPath('data.0.tingkat', 'Nasional')
                ->assertJsonPath('data.0.raw_date', '2026-11-05')
                ->assertJsonPath('data.0.tahun', 2026)
                ->assertJsonPath('data.0.semester', 'Ganjil')
                ->assertJsonPath('meta.total', 1);
        }
    }

    public function test_semester_genap_untuk_tanggal_selesai_bulan_maret_sampai_agustus(): void
    {
        $this->buatPrestasi('dosen', 'Lomba Genap', '2026-04-10');

        $this->sebagai()
            ->getJson(self::API)
            ->assertOk()
            ->assertJsonPath('data.0.semester', 'Genap');
    }

    public function test_filter_jenis_membatasi_tipe_pemilik(): void
    {
        $this->buatPrestasi('mahasiswa', 'Lomba Mahasiswa');
        $this->buatPrestasi('dosen', 'Lomba Dosen');

        $this->sebagai()
            ->getJson(self::API.'?jenis=dosen')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Lomba Dosen')
            ->assertJsonPath('data.0.tipe', 'Dosen');

        $this->getJson(self::API.'?jenis=mahasiswa')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Lomba Mahasiswa');

        $this->getJson(self::API)
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_parameter_tidak_valid_ditolak(): void
    {
        $this->sebagai()->getJson(self::API.'?per_page=201')->assertUnprocessable();
        $this->getJson(self::API.'?jenis=prodi')->assertUnprocessable();
        $this->getJson(self::API.'?per_page=200')->assertOk();
    }

    public function test_daftar_admin_kini_menghitung_semester(): void
    {
        $this->buatPrestasi();

        $this->sebagai()
            ->getJson('/api/v1/admin/prestasi?per_page=10')
            ->assertOk()
            ->assertJsonPath('data.0.tampilan.semester', 'Ganjil');
    }
}
