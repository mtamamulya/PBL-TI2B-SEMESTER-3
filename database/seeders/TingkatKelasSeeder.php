<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TingkatKelasSeeder extends Seeder
{
    public function run(): void
    {
        // 2. Isi Tabel Kelas (A, B, C, D, E)
        foreach (['A', 'B', 'C', 'D', 'E'] as $k) {
            DB::table('kelas')->updateOrInsert(
                ['jenis_kelas' => $k],
                ['keterangan' => "Kelas $k"]
            );
        }

        // 3. Isi Tabel Tingkat (1, 2, 3, 4)
        foreach ([1, 2, 3, 4] as $t) {
            DB::table('tingkat')->updateOrInsert(
                ['tingkat' => $t],
                ['keterangan' => "Tingkat $t"]
            );
        }
    }
}
