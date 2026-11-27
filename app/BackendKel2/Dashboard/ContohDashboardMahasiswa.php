<?php

namespace App\BackendKel2\Dashboard;

class ContohDashboardMahasiswa
{
    public static function data(): array
    {
        return [
            'ringkasan' => [
                'tahun_akademik' => '2026/2027',
                'jenis_semester' => 'Ganjil',
                'sks_tempuh' => 18,
                'sks_total' => 144,
                'semester_ke' => 5,
                'jumlah_mk' => 3,
                'sks_semester' => 11,
                'pengajuan_aktif' => 2,
                'dokumen_siap' => 1,
            ],
            'mata_kuliah' => [
                [
                    'mk' => 'Pemrograman Web Lanjut',
                    'dosen' => 'Dwi Rahmawati, S.Kom., M.Kom.',
                    'tingkat' => 2,
                    'semester' => 'Ganjil',
                    'tahun' => '2026/2027',
                    'sks' => 4,
                ],
                [
                    'mk' => 'Basis Data',
                    'dosen' => 'Budi Santoso, S.Kom., M.Kom.',
                    'tingkat' => 2,
                    'semester' => 'Ganjil',
                    'tahun' => '2026/2027',
                    'sks' => 3,
                ],
                [
                    'mk' => 'Jaringan Komputer',
                    'dosen' => 'Siti Aminah, S.ST., M.Eng.',
                    'tingkat' => 2,
                    'semester' => 'Ganjil',
                    'tahun' => '2026/2027',
                    'sks' => 4,
                ],
                [
                    'mk' => 'Struktur Data',
                    'dosen' => 'Andi Pratama, S.Kom., M.Kom.',
                    'tingkat' => 1,
                    'semester' => 'Genap',
                    'tahun' => '2025/2026',
                    'sks' => 3,
                ],
                [
                    'mk' => 'Matematika Komputasi',
                    'dosen' => 'Rina Lestari, S.Si., M.Sc.',
                    'tingkat' => 1,
                    'semester' => 'Ganjil',
                    'tahun' => '2025/2026',
                    'sks' => 4,
                ],
            ],
        ];
    }
}
