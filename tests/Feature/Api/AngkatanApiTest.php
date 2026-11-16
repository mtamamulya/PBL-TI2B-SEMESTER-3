<?php

namespace Tests\Feature\Api;

use App\Repositories\KurikulumDbStore;
use App\Repositories\MataKuliahDbStore;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Filter ?angkatan= pada GET /kurikulum dan GET /mata-kuliah (kurikulum yang berlaku bagi satu angkatan). */
class AngkatanApiTest extends TestCase
{
    use RefreshDatabase;

    private function perluKolomTahun(): void
    {
        if (! Schema::hasColumn('kurikulum', 'tahun_terbit') && ! Schema::hasColumn('kurikulum', 'angkatan_mulai')) {
            $this->markTestSkipped('Tabel kurikulum belum punya kolom tahun_terbit/angkatan_mulai.');
        }
    }

    private function kolomTahun(): string
    {
        return Schema::hasColumn('kurikulum', 'tahun_terbit') ? 'tahun_terbit' : 'angkatan_mulai';
    }

    private function buatKurikulum(string $nama, int $tahun, bool $aktif = true): int
    {
        $k = app(KurikulumDbStore::class)->create(['name' => $nama]);
        $id = (int) $k['id'];
        DB::table('kurikulum')->where('id_kurikulum', $id)->update([$this->kolomTahun() => $tahun, 'status' => $aktif]);

        return $id;
    }

    private function buatMataKuliah(int $idKurikulum, int $semester, string $kode): void
    {
        app(MataKuliahDbStore::class)->create([
            'kurikulum' => $idKurikulum, 'semester' => $semester, 'kode' => $kode, 'nama' => 'MK '.$kode,
            'sks_teori' => 2, 'sks_praktek' => 1, 'jam_teori' => 2, 'jam_praktek' => 2,
        ]);
    }

    /** Kurikulum mulai 2020, 2024, 2026. Mengembalikan id ketiganya. */
    private function isiData(): array
    {
        $this->seed(DatabaseSeeder::class);

        $k2020 = $this->buatKurikulum('Kurikulum 2020', 2020);
        $k2024 = $this->buatKurikulum('Kurikulum 2024', 2024);
        $k2026 = $this->buatKurikulum('Kurikulum 2026', 2026);

        $this->buatMataKuliah($k2020, 1, 'TRK101');
        $this->buatMataKuliah($k2024, 1, 'TRK201');
        $this->buatMataKuliah($k2024, 2, 'TRK202');
        $this->buatMataKuliah($k2026, 1, 'TRK301');

        return [$k2020, $k2024, $k2026];
    }

    public function test_mata_kuliah_mengikuti_kurikulum_angkatan(): void
    {
        $this->perluKolomTahun();
        $this->isiData();

        // 2025 belum ada kurikulum baru, jadi tetap memakai yang mulai 2024.
        $this->getJson('/api/v1/mata-kuliah?angkatan=2025')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.*.kode_mk', ['TRK201', 'TRK202'])
            ->assertJsonPath('meta.total', 2);

        $this->getJson('/api/v1/mata-kuliah?angkatan=2024')->assertOk()->assertJsonPath('data.*.kode_mk', ['TRK201', 'TRK202']);
        $this->getJson('/api/v1/mata-kuliah?angkatan=2026')->assertOk()->assertJsonPath('data.*.kode_mk', ['TRK301']);
        $this->getJson('/api/v1/mata-kuliah?angkatan=2022')->assertOk()->assertJsonPath('data.*.kode_mk', ['TRK101']);
    }

    public function test_angkatan_sebelum_kurikulum_pertama_menghasilkan_daftar_kosong(): void
    {
        $this->perluKolomTahun();
        $this->isiData();

        $this->getJson('/api/v1/mata-kuliah?angkatan=2019')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_angkatan_bisa_digabung_dengan_filter_semester(): void
    {
        $this->perluKolomTahun();
        $this->isiData();

        $this->getJson('/api/v1/mata-kuliah?angkatan=2025&semester=2')
            ->assertOk()
            ->assertJsonPath('data.*.kode_mk', ['TRK202']);
    }

    public function test_angkatan_dan_id_kurikulum_tidak_boleh_bersamaan(): void
    {
        $this->perluKolomTahun();
        [$k2020] = $this->isiData();

        $this->getJson('/api/v1/mata-kuliah?angkatan=2025&id_kurikulum='.$k2020)->assertStatus(422);
    }

    public function test_angkatan_tidak_valid_422(): void
    {
        $this->getJson('/api/v1/mata-kuliah?angkatan=1999')->assertStatus(422);
        $this->getJson('/api/v1/mata-kuliah?angkatan=abc')->assertStatus(422);
        $this->getJson('/api/v1/kurikulum?angkatan=2101')->assertStatus(422);
    }

    public function test_kurikulum_untuk_angkatan_dan_status_nonaktif_tetap_dihitung(): void
    {
        $this->perluKolomTahun();
        [, $k2024] = $this->isiData();
        DB::table('kurikulum')->where('id_kurikulum', $k2024)->update(['status' => false]);

        $this->getJson('/api/v1/kurikulum?angkatan=2025')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_kurikulum', $k2024)
            ->assertJsonPath('data.0.status', false);
    }

    public function test_tanpa_kolom_tahun_hasilnya_kosong_bukan_error(): void
    {
        if (Schema::hasColumn('kurikulum', 'tahun_terbit') || Schema::hasColumn('kurikulum', 'angkatan_mulai')) {
            $this->markTestSkipped('Tabel kurikulum sudah punya kolom tahun.');
        }

        $this->getJson('/api/v1/mata-kuliah?angkatan=2025')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/kurikulum?angkatan=2025')->assertOk()->assertJsonCount(0, 'data');
    }

    /** Bila kelak kolom angkatan_mulai ditambahkan (usulan B), nilainya didahulukan dari tahun_terbit. */
    public function test_angkatan_mulai_didahulukan_bila_kolomnya_ada(): void
    {
        if (! Schema::hasColumn('kurikulum', 'angkatan_mulai') || ! Schema::hasColumn('kurikulum', 'tahun_terbit')) {
            $this->markTestSkipped('Kolom angkatan_mulai belum ada.');
        }

        [, $k2024] = $this->isiData();
        DB::table('kurikulum')->where('id_kurikulum', $k2024)->update(['angkatan_mulai' => 2022]);

        $this->getJson('/api/v1/mata-kuliah?angkatan=2023')->assertOk()->assertJsonPath('data.*.kode_mk', ['TRK201', 'TRK202']);
    }
}
