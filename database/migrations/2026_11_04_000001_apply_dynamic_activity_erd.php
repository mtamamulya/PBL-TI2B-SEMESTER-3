<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(fn () => $this->transferExistingAnswers());
        Schema::rename('kegiatan_dosen', 'legacy_kegiatan_dosen');
    }

    private function definition(int $category, string $input, string $type): int
    {
        $existing = DB::table('isian_kategori')->where('id_kategori', $category)->where('input', $input)->value('id_isian_kategori');

        return $existing ?? DB::table('isian_kategori')->insertGetId([
            'id_kategori' => $category, 'input' => $input, 'jenis_input' => $type,
            'aturan' => 'NULL', 'jumlah_isian' => 1, 'created_at' => now(), 'updated_at' => now(),
        ], 'id_isian_kategori');
    }

    private function answer(object $activity, string $input, string $type, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }
        if ($type === 'tanggal') {
            $value = substr((string) $value, 0, 10);
        }
        $answer = [
            'id_kegiatan' => $activity->id_kegiatan,
            'id_isian_kategori' => $this->definition($activity->id_kategori, $input, $type),
            'isian' => (string) $value,
        ];
        if (! DB::table('detail_kegiatan')->where($answer)->exists()) {
            DB::table('detail_kegiatan')->insert($answer + [
                'created_at' => $activity->created_at ?? now(), 'updated_at' => $activity->updated_at ?? now(),
            ]);
        }
    }

    private function transferExistingAnswers(): void
    {
        $common = ['lokasi' => 'teks', 'deskripsi' => 'teks', 'tanggal_mulai' => 'tanggal', 'tanggal_selesai' => 'tanggal'];
        $lecturer = [
            'afiliasi' => 'teks', 'tahun_anggaran' => 'angka', 'tahun_pelaksanaan_ke' => 'angka',
            'lama_kegiatan_tahun' => 'angka', 'dana_dikti' => 'angka', 'dana_pt' => 'angka',
            'dana_institusi_lain' => 'angka', 'in_kind' => 'teks', 'mitra' => 'teks',
        ];
        foreach (DB::table('kategori')->cursor() as $category) {
            foreach ($common as $input => $type) {
                $this->definition($category->id_kategori, $input, $type);
            }
            if (preg_match('/penelitian|pengabdian/iu', $category->nama_kategori)) {
                foreach (['skim' => 'pilihan', 'kelompok_bidang' => 'pilihan'] + $lecturer as $input => $type) {
                    $this->definition($category->id_kategori, $input, $type);
                }
            }
        }
        DB::table('kegiatan')->orderBy('id_kegiatan')->chunkById(200, function ($activities) use ($lecturer) {
            foreach ($activities as $activity) {
                $old = DB::table('kegiatan_dosen')->where('id_kegiatan', $activity->id_kegiatan)->first();
                if ($old) {
                    DB::table('kegiatan')->where('id_kegiatan', $activity->id_kegiatan)->update([
                        'id_skim' => $activity->id_skim ?? $old->id_skim,
                        'id_kelompok_bidang' => $activity->id_kelompok_bidang ?? $old->id_kelompok_bidang,
                    ]);
                    foreach ($lecturer as $input => $type) {
                        $this->answer($activity, $input, $type, $old->{$input});
                    }
                    $this->answer($activity, 'skim', 'pilihan', DB::table('skim')->where('id_skim', $old->id_skim)->value('nama_skim'));
                    $this->answer($activity, 'kelompok_bidang', 'pilihan', DB::table('kelompok_bidang')->where('id_kelompok_bidang', $old->id_kelompok_bidang)->value('nama_kelompok_bidang'));
                }
            }
        }, 'id_kegiatan');
    }

    public function down(): void
    {
        Schema::rename('legacy_kegiatan_dosen', 'kegiatan_dosen');
    }
};
