<?php

namespace Tests\Feature\Api;

use App\Models\TahunAkademik;
use App\Models\User;
use App\Repositories\MahasiswaStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MahasiswaApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $idRole, string $email = ''): User
    {
        $nama = [1 => 'admin', 2 => 'kaprodi', 3 => 'dosen', 4 => 'mahasiswa'][$idRole];
        DB::table('roles')->insertOrIgnore(['id_role' => $idRole, 'nama_role' => $nama]);

        return User::firstOrCreate(
            ['email' => $email !== '' ? $email : "{$nama}@polines.ac.id"],
            ['id_role' => $idRole, 'password' => 'password', 'status' => true]
        );
    }

    private function sebagai(int $idRole, string $email = ''): static
    {
        return $this->actingAs($this->user($idRole, $email), 'sanctum');
    }

    public function test_index_dan_show_mengembalikan_allowed_statuses_sesuai_semester(): void
    {
        TahunAkademik::create(['tahun_akademik' => 'Ganjil 2026/2027', 'status' => true]);

        $mhsAktif = [
            'id' => '1',
            'nim' => '4.33.25.1.07',
            'nama' => 'Darryl Iwania',
            'level' => 'Tingkat 3',
            'kelas' => 'TI-3B',
            'status' => 'Aktif',
            'angkatan' => '2025', // Semester 3 (<= 8)
            'email' => 'darryll@mhs.polines.ac.id',
            'foto' => null,
        ];

        $mhsTingkatAkhir = [
            'id' => '2',
            'nim' => '4.33.22.1.05',
            'nama' => 'Bryan Pramasta',
            'level' => 'Tingkat 4',
            'kelas' => '-',
            'status' => 'Aktif',
            'angkatan' => '2022', // Semester 9 (> 8)
            'email' => 'bryan@mhs.polines.ac.id',
            'foto' => null,
        ];

        $store = $this->mock(MahasiswaStore::class);
        $store->shouldReceive('opsiStatus')->andReturn([
            'Aktif', 'Cuti', 'Drop Out', 'Skripsi/TA', 'Lulus', 'Meninggal Dunia',
        ]);
        $store->shouldReceive('all')->andReturn([$mhsAktif, $mhsTingkatAkhir]);
        $store->shouldReceive('find')->with('1')->andReturn($mhsAktif);
        $store->shouldReceive('riwayatKelas', 'kegiatan')->with('1')->andReturn([]);

        // GET /api/v1/mahasiswa
        $this->getJson('/api/v1/mahasiswa')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.allowed_statuses', ['Aktif', 'Cuti', 'Drop Out', 'Meninggal Dunia'])
            ->assertJsonPath('data.1.allowed_statuses', ['Aktif', 'Cuti', 'Drop Out', 'Skripsi/TA', 'Lulus', 'Meninggal Dunia']);

        // GET /api/v1/mahasiswa/1
        $this->getJson('/api/v1/mahasiswa/1')
            ->assertOk()
            ->assertJsonPath('data.semester_ke', 3)
            ->assertJsonPath('data.allowed_statuses', ['Aktif', 'Cuti', 'Drop Out', 'Meninggal Dunia']);
    }

    public function test_ubah_status_skripsi_ta_atau_lulus_ditolak_jika_belum_lebih_dari_8_semester(): void
    {
        TahunAkademik::create(['tahun_akademik' => 'Ganjil 2026/2027', 'status' => true]);

        $mhsSemester3 = [
            'id' => '1',
            'nim' => '4.33.25.1.07',
            'nama' => 'Darryl Iwania',
            'level' => 'Tingkat 3',
            'kelas' => 'TI-3B',
            'status' => 'Aktif',
            'angkatan' => '2025',
            'email' => 'darryll@mhs.polines.ac.id',
        ];

        $store = $this->mock(MahasiswaStore::class);
        $store->shouldReceive('opsiStatus')->andReturn([
            'Aktif', 'Cuti', 'Drop Out', 'Skripsi/TA', 'Lulus', 'Meninggal Dunia',
        ]);
        $store->shouldReceive('find')->with('1')->andReturn($mhsSemester3);

        $this->sebagai(1)
            ->patchJson('/api/v1/mahasiswa/1/status', ['status' => 'Skripsi/TA'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->sebagai(1)
            ->patchJson('/api/v1/mahasiswa/1/status', ['status' => 'Lulus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_admin_dapat_meluluskan_mahasiswa_di_atas_semester_8_per_individu(): void
    {
        TahunAkademik::create(['tahun_akademik' => 'Ganjil 2026/2027', 'status' => true]);

        $mhsSemester9 = [
            'id' => '2',
            'nim' => '4.33.22.1.05',
            'nama' => 'Bryan Pramasta',
            'level' => 'Tingkat 4',
            'kelas' => '-',
            'status' => 'Skripsi/TA',
            'angkatan' => '2022',
            'email' => 'bryan@mhs.polines.ac.id',
        ];

        $store = $this->mock(MahasiswaStore::class);
        $store->shouldReceive('opsiStatus')->andReturn([
            'Aktif', 'Cuti', 'Drop Out', 'Skripsi/TA', 'Lulus', 'Meninggal Dunia',
        ]);
        $store->shouldReceive('find')->with('2')->andReturn($mhsSemester9);
        $store->shouldReceive('update')->once()->andReturn(array_merge($mhsSemester9, ['status' => 'Lulus']));

        $this->sebagai(1)
            ->patchJson('/api/v1/mahasiswa/2/status', ['status' => 'Lulus'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'Lulus');
    }

    public function test_endpoint_luluskan_ta_massal_sudah_dihapus(): void
    {
        $this->sebagai(1)
            ->postJson('/api/v1/mahasiswa/luluskan-ta')
            ->assertStatus(405);
    }
}
