<?php

namespace App\BackendKel1\KategoriKegiatan;

use App\Models\Dosen;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tipe isian "pilihan baku": daftar pilihannya ditentukan sistem dari enum tabel
 * atau tabel master terkait (seperti tabel dosen) supaya data tetap konsisten.
 */
final class PilihanBaku
{
    public const DAFTAR = [
        'tingkat_kejuaraan' => [
            'label' => 'Pilihan: Tingkat kejuaraan',
            'tabel' => 'prestasi',
            'kolom' => 'tingkat_kejuaraan',
            'cadangan' => ['kabupaten/kota', 'provinsi', 'nasional', 'internasional'],
        ],
        'jenis_prestasi' => [
            'label' => 'Pilihan: Jenis prestasi',
            'tabel' => 'prestasi',
            'kolom' => 'jenis_prestasi',
            'cadangan' => ['akademik', 'non-akademik'],
        ],
    ];

    /** Tipe isian biasa yang diketik atau diunggah pengaju. */
    public const BIASA = [
        'teks' => 'Teks pendek',
        'paragraf' => 'Teks panjang',
        'angka' => 'Angka',
        'tanggal' => 'Tanggal',
        'berkas' => 'Unggah berkas',
    ];

    /** Semua nilai jenis_input yang sah. */
    public static function semuaJenis(): array
    {
        return array_merge(array_keys(self::BIASA), array_keys(self::DAFTAR), ['dosen']);
    }

    /** Daftar nilai mentah untuk tipe baku, atau null jika bukan tipe baku. */
    public static function nilai(string $jenis): ?array
    {
        if ($jenis === 'dosen') {
            return Dosen::query()
                ->orderBy('nama')
                ->pluck('id_dosen')
                ->map(fn ($id) => (string) $id)
                ->all();
        }

        if (! isset(self::DAFTAR[$jenis])) {
            return null;
        }

        $info = self::DAFTAR[$jenis];

        return self::enumTabel($info['tabel'], $info['kolom'], $info['cadangan']);
    }

    /** Daftar pasangan nilai dan label untuk opsi dropdown pilihan baku. */
    public static function opsi(string $jenis): ?array
    {
        if ($jenis === 'dosen') {
            return Dosen::query()
                ->orderBy('nama')
                ->get(['id_dosen', 'nama'])
                ->map(fn (Dosen $d) => [
                    'nilai' => (string) $d->id_dosen,
                    'label' => $d->nama,
                ])
                ->all();
        }

        $nilai = self::nilai($jenis);
        if ($nilai === null) {
            return null;
        }

        return array_map(fn (string $v) => [
            'nilai' => $v,
            'label' => Str::title($v),
        ], $nilai);
    }

    /** Daftar tipe isian untuk dropdown tipe di form admin. */
    public static function daftarTipe(): array
    {
        $out = [];

        foreach (self::BIASA as $nilai => $label) {
            $out[] = ['nilai' => $nilai, 'label' => $label, 'baku' => false];
        }

        foreach (self::DAFTAR as $nilai => $info) {
            $out[] = [
                'nilai' => $nilai,
                'label' => $info['label'],
                'baku' => true,
                'pilihan' => self::nilai($nilai),
            ];
        }

        $out[] = [
            'nilai' => 'dosen',
            'label' => 'Pilihan: Dosen',
            'baku' => true,
            'pilihan' => self::opsi('dosen'),
        ];

        return $out;
    }

    /** Membaca nilai ENUM langsung dari struktur kolom tabel di database. */
    private static function enumTabel(string $tabel, string $kolom, array $cadangan): array
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                $col = DB::selectOne("SHOW COLUMNS FROM `{$tabel}` WHERE Field = ?", [$kolom]);
                $type = $col->Type ?? $col->type ?? '';

                if (preg_match('/^enum\((.*)\)$/i', $type, $m)) {
                    preg_match_all("/'((?:[^']|'')*)'/", $m[1], $matches);
                    if (! empty($matches[1])) {
                        return array_map(fn (string $v) => str_replace("''", "'", $v), $matches[1]);
                    }
                }
            }
        } catch (\Throwable) {
            // Gunakan nilai cadangan bila tabel belum dimigrasi atau driver non-MySQL
        }

        return $cadangan;
    }
}