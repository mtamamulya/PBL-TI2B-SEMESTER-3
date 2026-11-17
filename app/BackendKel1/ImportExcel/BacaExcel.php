<?php

namespace App\BackendKel1\ImportExcel;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx as PembacaXlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;

/**
 * Membaca berkas .xlsx menjadi baris-baris berkunci nama kolom.
 *
 * Judul kolom dicocokkan longgar (huruf besar/kecil, spasi, garis bawah, dan tanda baca diabaikan),
 * jadi "SKS Praktik", "sks_praktik", dan "SKS-Praktik" dianggap sama. Sheet "Petunjuk" tidak ikut dibaca.
 */
class BacaExcel
{
    /**
     * @param  array<string, array<int, string>>  $kolom  kunci => daftar nama judul yang diterima (sudah dinormalkan)
     * @return array{error: string}|array{rows: array<int, array<string, mixed>>} rows diindeks dengan nomor baris Excel
     */
    public static function baca(string $path, array $kolom, int $maksBaris): array
    {
        try {
            $pembaca = new PembacaXlsx;
            $pembaca->setReadDataOnly(true);
            $buku = $pembaca->load($path);
            $sheet = $buku->getSheetByName('Data') ?? $buku->getSheet(0);

            $barisTertinggi = min($sheet->getHighestDataRow(), 5000);
            $semua = $sheet->rangeToArray('A1:'.$sheet->getHighestDataColumn().$barisTertinggi, null, false, false, false);
        } catch (Throwable) {
            return ['error' => 'Berkas tidak dapat dibaca. Pastikan berformat .xlsx dan berasal dari template yang diunduh.'];
        }

        // Judul = baris pertama yang tidak kosong.
        $nomorJudul = null;
        foreach ($semua as $i => $baris) {
            if (! self::kosong($baris)) {
                $nomorJudul = $i;
                break;
            }
        }
        if ($nomorJudul === null) {
            return ['error' => 'Berkas kosong. Isi data di bawah baris judul pada template.'];
        }

        $peta = [];
        foreach ($semua[$nomorJudul] as $indeks => $judul) {
            $peta[self::normal((string) $judul)] ??= $indeks;
        }

        $letak = [];
        $hilang = [];
        foreach ($kolom as $kunci => $namaDiterima) {
            $ketemu = null;
            foreach ($namaDiterima as $nama) {
                if (isset($peta[$nama])) {
                    $ketemu = $peta[$nama];
                    break;
                }
            }
            if ($ketemu === null) {
                $hilang[] = $namaDiterima[0];
            } else {
                $letak[$kunci] = $ketemu;
            }
        }
        if ($hilang) {
            return ['error' => 'Kolom wajib tidak ditemukan: '.implode(', ', $hilang).'. Gunakan template yang disediakan.'];
        }

        $rows = [];
        foreach ($semua as $i => $baris) {
            if ($i <= $nomorJudul || self::kosong($baris)) {
                continue;
            }
            $isi = [];
            foreach ($letak as $kunci => $indeks) {
                $isi[$kunci] = $baris[$indeks] ?? null;
            }
            $rows[$i + 1] = $isi;   // nomor baris Excel (indeks mulai 0 -> baris mulai 1)
        }

        if (count($rows) > $maksBaris) {
            return ['error' => 'Terlalu banyak baris ('.count($rows).'). Maksimal '.number_format($maksBaris, 0, ',', '.').' baris per berkas; pecah menjadi beberapa berkas.'];
        }
        if (! $rows) {
            return ['error' => 'Tidak ada data di bawah baris judul.'];
        }

        return ['rows' => $rows];
    }

    /** "SKS Praktik" -> "skspraktik" */
    public static function normal(string $judul): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($judul))) ?? '';
    }

    /** Angka bulat dari sel Excel (2, 2.0, "2"); selain itu null. */
    public static function bulat(mixed $nilai): ?int
    {
        if (is_int($nilai)) {
            return $nilai;
        }
        if (is_float($nilai)) {
            return floor($nilai) === $nilai ? (int) $nilai : null;
        }
        if (is_string($nilai) && preg_match('/^\s*-?\d+\s*$/', $nilai)) {
            return (int) trim($nilai);
        }

        return null;
    }

    /** Teks dari sel Excel; angka utuh ditulis tanpa notasi ilmiah. */
    public static function teks(mixed $nilai): string
    {
        if ($nilai === null) {
            return '';
        }
        if (is_float($nilai) && floor($nilai) === $nilai) {
            return (string) (int) $nilai;
        }

        return trim((string) $nilai);
    }

    /** Tanggal Y-m-d dari sel Excel (angka serial tanggal, "2022-01-12", atau "12/01/2022"); selain itu null. */
    public static function tanggal(mixed $nilai): ?string
    {
        if ($nilai === null || (is_string($nilai) && trim($nilai) === '')) {
            return null;
        }
        try {
            if (is_int($nilai) || is_float($nilai)) {
                return Date::excelToDateTimeObject((float) $nilai)->format('Y-m-d');
            }
            $t = trim((string) $nilai);
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return $t;
            }
            if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $t, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }
        } catch (Throwable) {
        }

        return null;
    }

    private static function kosong(array $baris): bool
    {
        foreach ($baris as $sel) {
            if ($sel !== null && trim((string) $sel) !== '') {
                return false;
            }
        }

        return true;
    }
}
