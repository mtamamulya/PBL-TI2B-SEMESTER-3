<?php

namespace Tests\Feature\BackendKel2;

use App\BackendKel2\Dokumen\JenisDokumenBaku;
use App\Models\JenisDokumen;
use Database\Seeders\JenisDokumenSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JenisDokumenSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_menambahkan_surat_hasil_tanpa_duplikat(): void
    {
        $this->seed(JenisDokumenSeeder::class);
        $this->seed(JenisDokumenSeeder::class);

        $this->assertDatabaseCount('jenis_dokumen', 6);
        $this->assertSame(1, JenisDokumen::query()
            ->where('nama_jenis_dokumen', JenisDokumenBaku::SURAT_HASIL)
            ->count());
        $this->assertDatabaseHas('jenis_dokumen', [
            'nama_jenis_dokumen' => JenisDokumenBaku::SURAT_HASIL,
            'keterangan' => 'Surat hasil pengajuan',
        ]);
    }
}
