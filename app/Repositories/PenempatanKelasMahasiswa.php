<?php

namespace App\Repositories;

use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Support\NimMahasiswa;
use App\Support\SemesterAkademik;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PenempatanKelasMahasiswa
{
    public function tahunAkademikAktif(): ?object
    {
        return DB::table('tahun_akademik')->where('status', true)->orderByDesc('id_tahun_akademik')->first();
    }

    /**
     * Tempatkan satu mahasiswa pada tahun akademik $ta.
     * Hasil: 'dibuat' | 'sudah-ada' | 'skripsi-ta' | 'belum-mulai' | 'nim-tidak-valid'.
     */
    public function tempatkan(Mahasiswa $m, object $ta): string
    {
        $term = SemesterAkademik::parse((string) $ta->tahun_akademik);
        if (! $term) {
            throw new RuntimeException("Format tahun akademik '{$ta->tahun_akademik}' tidak dikenali (contoh: Ganjil 2026/2027).");
        }

        $nim = NimMahasiswa::parse((string) $m->nim);
        if (! $nim) {
            return 'nim-tidak-valid';
        }

        $semester = SemesterAkademik::semester((int) $m->tahun_masuk, $term);

        if ($semester < 1) {
            return 'belum-mulai';
        }

        // Tingkat 4 yang sudah melewati semester reguler (sinkronisasi Juli) -> status berubah jadi Skripsi/TA (belum Lulus)
        if ($semester > NimMahasiswa::maxSemester($nim['jenjang_kode'] ?? $nim['jenjang'] ?? '4')) {
            DB::table('mahasiswa')
                ->where('id_mahasiswa', $m->id_mahasiswa)
                ->update([
                    'status_mahasiswa' => 'Skripsi/TA',
                    'updated_at' => now(),
                ]);

            // Akun tetap aktif agar mahasiswa Skripsi/TA masih bisa login untuk pengajuan/bimbingan
            DB::table('users')
                ->where('id_user', $m->id_user)
                ->update(['status' => true]);

            return 'skripsi-ta';
        }

        $sudahAda = DB::table('enrollment_mahasiswa_tingkat_kelas as emt')
            ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emt.id_enrollment_tingkat_kelas')
            ->where('emt.id_mahasiswa', $m->id_mahasiswa)
            ->where('etk.id_tahun_akademik', $ta->id_tahun_akademik)
            ->exists();
        if ($sudahAda) {
            return 'sudah-ada';
        }

        $tingkat = SemesterAkademik::tingkat($semester);

        DB::transaction(function () use ($m, $ta, $nim, $tingkat) {
            DB::table('enrollment_mahasiswa_tingkat_kelas')->insert([
                'id_enrollment_tingkat_kelas' => $this->idEnrollmentTingkatKelas($nim['kelas'], $tingkat, (int) $ta->id_tahun_akademik),
                'id_mahasiswa' => $m->id_mahasiswa,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return 'dibuat';
    }

    /**
     * Tempatkan SEMUA mahasiswa berstatus 'Aktif' pada satu tahun akademik.
     *
     * @return array{dibuat: int, 'sudah-ada': int, 'skripsi-ta': int, 'belum-mulai': int, 'nim-tidak-valid': int}
     */
    public function buatUntukSemester(int $idTahunAkademik): array
    {
        $ta = DB::table('tahun_akademik')->where('id_tahun_akademik', $idTahunAkademik)->first();
        if (! $ta) {
            throw new RuntimeException('Tahun akademik tidak ditemukan.');
        }

        $ringkasan = [
            'dibuat' => 0,
            'sudah-ada' => 0,
            'skripsi-ta' => 0,
            'belum-mulai' => 0,
            'nim-tidak-valid' => 0,
        ];

        Mahasiswa::query()
            ->where('status_mahasiswa', 'Aktif')
            ->whereHas('user', fn ($q) => $q->where('status', true))
            ->orderBy('id_mahasiswa')
            ->get()
            ->each(function (Mahasiswa $m) use ($ta, &$ringkasan) {
                $ringkasan[$this->tempatkan($m, $ta)]++;
            });

        return $ringkasan;
    }

    /**
     * Ubah semua mahasiswa berstatus 'Skripsi/TA' menjadi 'Lulus' (dipakai saat yudisium/wisuda sekitar September).
     */
    public function luluskanSkripsiTa(): int
    {
        $target = Mahasiswa::query()
            ->where('status_mahasiswa', 'Skripsi/TA')
            ->get(['id_mahasiswa', 'id_user']);

        if ($target->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($target) {
            Mahasiswa::query()
                ->whereIn('id_mahasiswa', $target->pluck('id_mahasiswa'))
                ->update([
                    'status_mahasiswa' => 'Lulus',
                    'updated_at' => now(),
                ]);

            DB::table('users')
                ->whereIn('id_user', $target->pluck('id_user'))
                ->update(['status' => false]);
        });

        return $target->count();
    }

    public function kelasSaatIni(int $idMahasiswa, ?object $ta = null): ?array
    {
        $ta ??= $this->tahunAkademikAktif();
        if (! $ta) {
            return null;
        }

        $r = DB::table('enrollment_mahasiswa_tingkat_kelas as emt')
            ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emt.id_enrollment_tingkat_kelas')
            ->join('kelas as kl', 'kl.id_kelas', '=', 'etk.id_kelas')
            ->join('tingkat as tg', 'tg.id_tingkat', '=', 'etk.id_tingkat')
            ->where('emt.id_mahasiswa', $idMahasiswa)
            ->where('etk.id_tahun_akademik', $ta->id_tahun_akademik)
            ->first(['tg.tingkat', 'kl.jenis_kelas']);

        return $r ? $this->bentukKelas((int) $r->tingkat, $r->jenis_kelas, $ta) : null;
    }

    public function kelasSaatIniSemua(?object $ta = null): array
    {
        $ta ??= $this->tahunAkademikAktif();
        if (! $ta) {
            return [];
        }

        $hasil = [];
        $baris = DB::table('enrollment_mahasiswa_tingkat_kelas as emt')
            ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emt.id_enrollment_tingkat_kelas')
            ->join('kelas as kl', 'kl.id_kelas', '=', 'etk.id_kelas')
            ->join('tingkat as tg', 'tg.id_tingkat', '=', 'etk.id_tingkat')
            ->where('etk.id_tahun_akademik', $ta->id_tahun_akademik)
            ->get(['emt.id_mahasiswa', 'tg.tingkat', 'kl.jenis_kelas']);

        foreach ($baris as $r) {
            $hasil[(int) $r->id_mahasiswa] = $this->bentukKelas((int) $r->tingkat, $r->jenis_kelas, $ta);
        }

        return $hasil;
    }

    private function bentukKelas(int $tingkat, string $kelas, object $ta): array
    {
        return [
            'tingkat' => $tingkat,
            'kelas' => $kelas,
            'label' => NimMahasiswa::label($tingkat, $kelas),
            'tahun_akademik' => $ta->tahun_akademik,
        ];
    }

    private function idEnrollmentTingkatKelas(string $huruf, int $tingkat, int $idTahunAkademik): int
    {
        $idKelas = DB::table('kelas')->where('jenis_kelas', $huruf)->value('id_kelas');
        $idTingkat = DB::table('tingkat')->where('tingkat', $tingkat)->value('id_tingkat');
        $idProdi = Prodi::query()->orderBy('id_prodi')->value('id_prodi');

        if (! $idKelas) {
            throw new RuntimeException("Tabel kelas belum berisi kelas '{$huruf}'.");
        }
        if (! $idTingkat) {
            throw new RuntimeException("Tabel tingkat belum berisi tingkat {$tingkat}.");
        }
        if (! $idProdi) {
            throw new RuntimeException('Tabel prodi masih kosong.');
        }

        $kunci = [
            'id_prodi' => $idProdi,
            'id_tingkat' => $idTingkat,
            'id_kelas' => $idKelas,
            'id_tahun_akademik' => $idTahunAkademik,
        ];

        $id = DB::table('enrollment_tingkat_kelas')->where($kunci)->value('id_enrollment_tingkat_kelas')
            ?: DB::table('enrollment_tingkat_kelas')->insertGetId($kunci + ['created_at' => now(), 'updated_at' => now()]);

        return (int) $id;
    }
}
