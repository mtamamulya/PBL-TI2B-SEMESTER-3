<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UjiDataMkTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('prodi')->insert(['kode_prodi' => 'TRK', 'nama_prodi' => 'Teknologi Rekayasa Komputer', 'jenjang' => 'D4']);
        DB::table('roles')->insertOrIgnore([['id_role' => 4, 'nama_role' => 'mahasiswa']]);
        DB::table('kurikulum')->insert([
            'id_prodi' => 1, 'nama_kurikulum' => 'Kurikulum Asli', 'status' => 1, 'link' => 'kurikulum/asli.pdf', 'tahun_terbit' => 2026,
        ]);
        DB::table('mata_kuliah')->insert([
            'id_kurikulum' => 1, 'semester' => 3, 'kode_mk' => 'TRK301', 'nama_mk' => 'Pemrograman Web Lanjut',
            'sks_teori' => 2, 'sks_praktik' => 2, 'jam_teori' => 2, 'jam_praktek' => 4,
        ]);
    }

    private function hitung(): array
    {
        return [
            DB::table('kurikulum')->where('nama_kurikulum', 'like', '[UJI]%')->count(),
            DB::table('mata_kuliah')->where('kode_mk', 'like', 'UJI%')->count(),
            DB::table('mahasiswa')->where('nim', 'like', 'UJI%')->count(),
        ];
    }

    public function test_pasang_membuat_data_dan_aman_diulang(): void
    {
        $this->artisan('uji:data-mk', ['--yes' => true])->assertExitCode(0);
        $this->assertSame([3, 90, 4], $this->hitung());

        $this->artisan('uji:data-mk', ['--yes' => true])->assertExitCode(0);
        $this->assertSame([3, 90, 4], $this->hitung());

        $k21 = DB::table('kurikulum')->where('nama_kurikulum', '[UJI] Kurikulum 2021')->first();
        $this->assertSame(2021, (int) $k21->tahun_terbit);
        $this->assertSame('kurikulum/asli.pdf', $k21->link);
        $this->assertSame(40, DB::table('mata_kuliah')->where('id_kurikulum', $k21->id_kurikulum)->count());
        $this->assertSame(5, DB::table('mata_kuliah')->where('id_kurikulum', $k21->id_kurikulum)->where('semester', 8)->count());
        $this->assertSame(2022, (int) DB::table('mahasiswa')->where('nim', 'UJI2022001')->value('tahun_masuk'));
    }

    public function test_hapus_membersihkan_data_uji_dan_tidak_menyentuh_data_asli(): void
    {
        $this->artisan('uji:data-mk', ['--yes' => true])->assertExitCode(0);
        $this->artisan('uji:data-mk', ['--yes' => true, '--hapus' => true])->assertExitCode(0);

        $this->assertSame([0, 0, 0], $this->hitung());
        $this->assertSame(0, DB::table('users')->where('email', 'like', '%@uji.trk.test')->count());
        $this->assertSame(1, DB::table('kurikulum')->count());
        $this->assertSame(1, DB::table('mata_kuliah')->count());
    }

    public function test_tanpa_mahasiswa(): void
    {
        $this->artisan('uji:data-mk', ['--yes' => true, '--tanpa-mahasiswa' => true])->assertExitCode(0);

        $this->assertSame([3, 90, 0], $this->hitung());
    }

    public function test_batal_jika_konfirmasi_ditolak(): void
    {
        $this->artisan('uji:data-mk')->expectsConfirmation(
            'TULIS data uji pada database "'.DB::getDatabaseName().'". Lanjut?', 'no'
        )->assertExitCode(1);

        $this->assertSame([0, 0, 0], $this->hitung());
    }
}
