<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MataKuliahKurikulumProdiTest extends TestCase
{
    use RefreshDatabase;

    public function test_katalog_mata_kuliah_dibedakan_berdasarkan_prodi_dan_angkatan(): void
    {
        $idProdiA = DB::table('prodi')->insertGetId([
            'kode_prodi' => 'PRODI-A',
            'nama_prodi' => 'Prodi A',
            'jenjang' => 'D4',
        ]);
        $idProdiB = DB::table('prodi')->insertGetId([
            'kode_prodi' => 'PRODI-B',
            'nama_prodi' => 'Prodi B',
            'jenjang' => 'D4',
        ]);

        foreach ([
            [$idProdiA, 'Mata Kuliah Prodi A'],
            [$idProdiB, 'Mata Kuliah Prodi B'],
        ] as [$idProdi, $namaMataKuliah]) {
            $idKurikulum = DB::table('kurikulum')->insertGetId([
                'id_prodi' => $idProdi,
                'nama_kurikulum' => 'Kurikulum '.$idProdi,
                'tahun_terbit' => 2024,
                'status' => true,
            ]);
            DB::table('mata_kuliah')->insert([
                'id_kurikulum' => $idKurikulum,
                'semester' => 1,
                'kode_mk' => 'MK'.$idProdi,
                'nama_mk' => $namaMataKuliah,
                'sks_teori' => 2,
                'sks_praktik' => 1,
                'jam_teori' => 2,
                'jam_praktek' => 1,
            ]);
        }

        $this->getJson('/api/v1/mata-kuliah?angkatan=2025&kode_prodi=PRODI-A')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_mk', 'Mata Kuliah Prodi A');

        $this->getJson('/api/v1/mata-kuliah?angkatan=2025&kode_prodi=PRODI-B')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_mk', 'Mata Kuliah Prodi B');
    }
}
