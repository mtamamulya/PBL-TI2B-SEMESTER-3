<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CATEGORIES = [
        'Lomba' => 'Kompetisi, perlombaan, atau pencapaian mahasiswa.',
        'Volunteer' => 'Kegiatan kerelawanan dan kontribusi sosial.',
        'KKL' => 'Kuliah Kerja Lapangan dan kegiatan kunjungan lapangan.',
        'Magang' => 'Praktik kerja atau pengalaman magang profesional.',
        'Pengabdian' => 'Kegiatan pengabdian kepada masyarakat.',
        'Penelitian' => 'Riset, penelitian, dan kegiatan ilmiah mahasiswa.',
    ];

    public function up(): void
    {
        $now = now();
        $jenisMahasiswa = DB::table('jenis_kegiatan')
            ->where('nama_jenis_kegiatan', 'Kegiatan Mahasiswa')
            ->first();

        if (! $jenisMahasiswa) {
            return;
        }

        $idJenisMahasiswa = $jenisMahasiswa->id_jenis_kegiatan;
        $kategoriAda = DB::table('kategori')
            ->where('id_jenis_kegiatan', $idJenisMahasiswa)
            ->pluck('nama_kategori')
            ->map(fn (string $category) => $this->kategoriKey($category))
            ->all();

        foreach (self::CATEGORIES as $name => $description) {
            $key = $this->kategoriKey($name);
            if (! in_array($key, $kategoriAda, true)) {
                DB::table('kategori')->insert([
                    'id_jenis_kegiatan' => $idJenisMahasiswa,
                    'nama_kategori' => $name,
                    'deskripsi' => $description,
                    'kebutuhan' => 'kegiatan',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $kategoriAda[] = $key;
            }
        }
    }

    private function kategoriKey(string $name): string
    {
        $name = mb_strtolower(trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $name) ?? $name));

        return $name === 'kuliah kerja lapangan' ? 'kkl' : $name;
    }

    public function down(): void
    {
        $idJenisMahasiswa = DB::table('jenis_kegiatan')
            ->where('nama_jenis_kegiatan', 'Kegiatan Mahasiswa')
            ->value('id_jenis_kegiatan');

        if (! $idJenisMahasiswa) {
            return;
        }

        DB::table('kategori')
            ->where('id_jenis_kegiatan', $idJenisMahasiswa)
            ->whereIn('nama_kategori', ['KKL', 'Penelitian'])
            ->whereIn('deskripsi', [
                self::CATEGORIES['KKL'],
                self::CATEGORIES['Penelitian'],
            ])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('kegiatan')
                    ->whereColumn('kegiatan.id_kategori', 'kategori.id_kategori');
            })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('isian_kategori')
                    ->whereColumn('isian_kategori.id_kategori', 'kategori.id_kategori');
            })
            ->delete();
    }
};
