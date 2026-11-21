<?php

namespace App\Support\Laporan;

/**
 * Menyusun laporan data prestasi (semua / dosen / mahasiswa) per kurun waktu tahun,
 * lalu mengubahnya menjadi PDF atau Excel. Memakai PdfTabel dan XlsxTabel yang sama dengan laporan kegiatan.
 */
class LaporanPrestasi
{
    /**
     * @param  string  $jenis  'semua' | 'dosen' | 'mahasiswa'
     * @param  array<int, array<string, mixed>>  $rows  baris rekap (kunci: nama, kategori, tipe, pemilik, tingkat, capaian, tanggal, tanggal_iso, tahun, semester)
     * @return array{judul: string, info: string[], kolom: array, data: array, nama_file: string, jumlah: int}
     */
    public static function susun(string $jenis, array $rows, int $dari, int $sampai): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => $r['tahun'] >= $dari && $r['tahun'] <= $sampai));

        usort($rows, fn ($a, $b) => [$a['tanggal_iso'], $a['nama']] <=> [$b['tanggal_iso'], $b['nama']]);

        $semua = $jenis === 'semua';
        $mhs = $jenis === 'mahasiswa';
        $periode = $dari === $sampai ? "Tahun $dari" : "Tahun $dari – $sampai";

        $kolom = $semua
            ? [
                ['label' => 'No', 'lebar' => 30, 'rata' => 'C', 'angka' => true, 'lebar_xls' => 6],
                ['label' => 'Nama Prestasi', 'lebar' => 190, 'lebar_xls' => 36],
                ['label' => 'Tipe', 'lebar' => 60, 'rata' => 'C', 'lebar_xls' => 12],
                ['label' => 'Kategori Kegiatan', 'lebar' => 95, 'lebar_xls' => 20],
                ['label' => 'Pemilik Prestasi', 'lebar' => 120, 'lebar_xls' => 24],
                ['label' => 'Tingkat', 'lebar' => 70, 'lebar_xls' => 14],
                ['label' => 'Capaian', 'lebar' => 70, 'lebar_xls' => 14],
                ['label' => 'Tanggal', 'lebar' => 80, 'lebar_xls' => 16],
                ['label' => 'Semester', 'lebar' => 55, 'rata' => 'C', 'lebar_xls' => 11],
            ]
            : [
                ['label' => 'No', 'lebar' => 30, 'rata' => 'C', 'angka' => true, 'lebar_xls' => 6],
                ['label' => 'Nama Prestasi', 'lebar' => 210, 'lebar_xls' => 38],
                ['label' => 'Kategori Kegiatan', 'lebar' => 100, 'lebar_xls' => 20],
                ['label' => $mhs ? 'Mahasiswa' : 'Dosen', 'lebar' => 130, 'lebar_xls' => 24],
                ['label' => 'Tingkat', 'lebar' => 75, 'lebar_xls' => 14],
                ['label' => 'Capaian', 'lebar' => 80, 'lebar_xls' => 14],
                ['label' => 'Tanggal', 'lebar' => 85, 'lebar_xls' => 16],
                ['label' => 'Semester', 'lebar' => 60, 'rata' => 'C', 'lebar_xls' => 11],
            ];

        $data = [];
        foreach ($rows as $i => $r) {
            $umum = [$r['nama'], $r['kategori']];
            $akhir = [self::isi($r['tingkat']), self::isi($r['capaian']), $r['tanggal'], self::isi($r['semester'])];

            $data[] = $semua
                ? array_merge([$i + 1, $r['nama'], $r['tipe'], $r['kategori'], $r['pemilik']], $akhir)
                : array_merge([$i + 1], $umum, [$r['pemilik']], $akhir);
        }

        // Ringkasan: per tipe pemilik (laporan semua) atau per kategori kegiatan (laporan dosen/mahasiswa)
        $hitung = array_count_values(array_column($rows, $semua ? 'tipe' : 'kategori'));
        ksort($hitung);
        $ringkas = [];
        foreach ($hitung as $k => $n) {
            $ringkas[] = "$k: $n";
        }

        $info = [
            'Program Studi Teknologi Rekayasa Komputer – Politeknik Negeri Semarang',
            'Periode: '.$periode.'   |   Berdasarkan tanggal selesai',
            'Total prestasi: '.count($rows).($ringkas ? '   ('.implode(', ', $ringkas).')' : ''),
        ];

        return [
            'judul' => $semua ? 'LAPORAN SELURUH DATA PRESTASI' : 'LAPORAN DATA PRESTASI '.($mhs ? 'MAHASISWA' : 'DOSEN'),
            'info' => $info,
            'kolom' => $kolom,
            'data' => $data ?: [self::kosong(count($kolom))],
            'nama_file' => 'laporan-prestasi-'.$jenis.'-'.($dari === $sampai ? $dari : "$dari-$sampai"),
            'jumlah' => count($rows),
        ];
    }

    public static function pdf(array $lap, string $oleh, string $waktuCetak): string
    {
        return (new PdfTabel($lap['judul'], $lap['info'], $lap['kolom'], $lap['data'], 'Dicetak oleh '.$oleh.' pada '.$waktuCetak))->render();
    }

    public static function xlsx(array $lap, string $oleh, string $waktuCetak): string
    {
        // Excel memakai lebar dalam jumlah karakter ('lebar_xls'), PDF memakai lebar relatif ('lebar').
        $kolom = [];
        foreach ($lap['kolom'] as $k) {
            $k['lebar'] = $k['lebar_xls'];
            $kolom[] = $k;
        }

        return (new XlsxTabel('Laporan Prestasi', $lap['judul'], $lap['info'], $kolom, $lap['data'], 'Dicetak oleh '.$oleh.' pada '.$waktuCetak))->render();
    }

    private static function isi(mixed $nilai): string
    {
        $teks = trim((string) $nilai);

        return $teks === '' ? '-' : $teks;
    }

    private static function kosong(int $kolom): array
    {
        $baris = array_fill(0, $kolom, '');
        $baris[1] = 'Tidak ada prestasi pada periode ini.';

        return $baris;
    }
}
