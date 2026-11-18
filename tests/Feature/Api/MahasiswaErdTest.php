<?php

namespace Tests\Feature\Api;

use App\Repositories\MahasiswaStore;
use Database\Seeders\TingkatKelasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MahasiswaErdTest extends TestCase
{
    use RefreshDatabase;

    public function test_detail_tanpa_enrollment_hanya_memuat_data_profil_dan_riwayat_asli(): void
    {
        $store = $this->mock(MahasiswaStore::class);
        $store->shouldReceive('find')->with('7')->andReturn([
            'id' => '7', 'nama' => 'Uji', 'level' => '-', 'kelas' => '-',
            'tanggal_lahir' => null, 'telepon' => null, 'password_hash' => 'internal',
        ]);
        $store->shouldReceive('riwayatKelas', 'kegiatan')->with('7')->andReturn(null);

        $this->getJson(route('api.mahasiswa.show', '7'))
            ->assertOk()
            ->assertJsonPath('data.nama', 'Uji')
            ->assertJsonMissingPath('data.tanggal_lahir')
            ->assertJsonMissingPath('data.telepon')
            ->assertJsonMissingPath('data.password_hash')
            ->assertJsonPath('history', []);
    }

    public function test_master_tingkat_dan_kelas_dapat_diisi_ulang(): void
    {
        $this->seed(TingkatKelasSeeder::class);
        $this->seed(TingkatKelasSeeder::class);

        $this->assertSame(['A', 'B', 'C', 'D', 'E'], DB::table('kelas')->orderBy('jenis_kelas')->pluck('jenis_kelas')->all());
        $this->assertEquals([1, 2, 3, 4], DB::table('tingkat')->orderBy('tingkat')->pluck('tingkat')->all());
    }
}
