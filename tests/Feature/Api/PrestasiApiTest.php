<?php

namespace Tests\Feature\Api;

use App\Models\Prestasi;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** API baca publik: daftar prestasi dan detail prestasi. */
class PrestasiApiTest extends TestCase
{
    use RefreshDatabase;

    private function isiData(): array
    {
        $this->seed(DatabaseSeeder::class);

        $mahasiswa = User::firstOrCreate(
            ['email' => 'mahasiswa.prestasi@trk.ac.id'],
            ['id_role' => 4, 'password' => 'password', 'status' => true]
        );

        $prestasi = Prestasi::create([
            'id_user' => $mahasiswa->id_user,
            'created_by' => $mahasiswa->id_user,
            'id_kegiatan' => null,
            'jenis_prestasi' => 'akademik',
            'bidang_lomba' => 'Rekayasa Perangkat Lunak',
            'nama_lomba' => 'Gemastik XIX',
            'penyelenggara' => 'Kemendikbudristek',
            'lokasi' => 'Semarang',
            'capaian' => 'Juara 1',
            'tingkat_kejuaraan' => 'nasional',
            'tanggal_mulai' => '2026-09-10',
            'tanggal_selesai' => '2026-09-12',
            'bentuk_partisipasi' => 'tim',
        ]);

        return [$prestasi, $mahasiswa];
    }

    public function test_daftar_prestasi_berformat_standar_dengan_paginasi(): void
    {
        [$prestasi] = $this->isiData();

        $this->getJson('/api/v1/prestasi?per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [[
                    'id_prestasi',
                    'id_kegiatan',
                    'jenis_prestasi',
                    'bidang_lomba',
                    'capaian',
                    'kegiatan',
                ]],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('data.0.id_prestasi', $prestasi->id_prestasi)
            ->assertJsonPath('data.0.jenis_prestasi', 'akademik')
            ->assertJsonPath('data.0.bidang_lomba', 'Rekayasa Perangkat Lunak');
    }

    public function test_detail_prestasi_memuat_data_dan_bukti_prestasi(): void
    {
        [$prestasi] = $this->isiData();

        $this->getJson('/api/v1/prestasi/'.$prestasi->id_prestasi)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_prestasi', $prestasi->id_prestasi)
            ->assertJsonPath('data.capaian', 'Juara 1')
            ->assertJsonPath('data.jenis_prestasi', 'akademik')
            ->assertJsonStructure([
                'data' => [
                    'id_prestasi',
                    'kegiatan',
                    'bukti_prestasi',
                ],
            ]);
    }

    public function test_prestasi_id_tidak_ada_mengembalikan_404_berformat_gagal(): void
    {
        $this->getJson('/api/v1/prestasi/999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_parameter_per_page_tidak_valid_mengembalikan_422(): void
    {
        $this->getJson('/api/v1/prestasi?per_page=500')
            ->assertStatus(422);
    }
}
