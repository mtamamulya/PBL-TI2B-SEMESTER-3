<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterRisetDosenSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $jenis = $this->id('jenis_kegiatan', 'id_jenis_kegiatan', ['nama_jenis_kegiatan' => 'Kegiatan Dosen']);
            foreach (['Penelitian', 'Pengabdian'] as $nama) {
                $kategori = $this->id('kategori', 'id_kategori', ['id_jenis_kegiatan' => $jenis, 'nama_kategori' => $nama]);
                foreach ([
                    'penyelenggara' => 'teks', 'tahun_penyelenggaraan' => 'angka', 'tanggal_mulai' => 'tanggal',
                    'tanggal_selesai' => 'tanggal', 'tahun_pelaksanaan_ke' => 'angka', 'lama_kegiatan_tahun' => 'angka',
                    'lokasi' => 'teks', 'mitra' => 'teks', 'tahun_anggaran' => 'angka', 'afiliasi' => 'teks',
                    'dana_dikti' => 'rupiah', 'dana_pt' => 'rupiah', 'dana_institusi_lain' => 'rupiah', 'in_kind' => 'rupiah',
                ] as $input => $tipe) {
                    $this->id('isian_kategori', 'id_isian_kategori', ['id_kategori' => $kategori, 'input' => $input], [
                        'jenis_input' => $tipe,
                        'aturan' => in_array($input, ['tanggal_mulai', 'tanggal_selesai', 'lokasi', 'tahun_pelaksanaan_ke', 'lama_kegiatan_tahun'], true) ? 'NOT NULL' : 'NULL',
                    ]);
                }
            }
            foreach ([
                'Penelitian Dasar Unggulan Perguruan Tinggi', 'Penelitian Terapan', 'Penelitian Pengembangan',
                'Pengabdian Kepada Masyarakat Unggulan Perguruan Tinggi', 'Pengabdian Kemitraan Masyarakat',
                'Pengabdian Pemberdayaan Berbasis Kewilayahan',
            ] as $skim) {
                $this->id('skim', 'id_skim', ['nama_skim' => $skim]);
            }
            foreach (['Teknologi Informasi', 'Energi', 'Pendidikan', 'Kesehatan', 'Pertanian'] as $bidang) {
                $this->id('kelompok_bidang', 'id_kelompok_bidang', ['nama_kelompok_bidang' => $bidang]);
            }
        });
    }

    private function id(string $table, string $key, array $match, array $defaults = []): int
    {
        return DB::table($table)->where($match)->value($key)
            ?? DB::table($table)->insertGetId($match + $defaults, $key);
    }
}
