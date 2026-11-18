<?php

namespace App\BackendKel1\KategoriKegiatan;

/**
 * Tipe isian "pilihan baku": daftar pilihannya ditentukan sistem (bukan diketik admin),
 * nilainya sama dengan enum di tabel lain supaya data konsisten.
 * Menambah pilihan baku baru cukup menambah satu baris di DAFTAR.
 * Tipe `dosen` tidak punya daftar tetap: pilihannya diambil dari tabel dosen.
 */
final class PilihanBaku
{
    public const DAFTAR = [
        // sama dengan enum prestasi.tingkat_kejuaraan / detail_kegiatan.tingkat_kejuaraan
        'tingkat_kejuaraan' => ['label' => 'Pilihan: Tingkat kejuaraan', 'nilai' => ['kabupaten/kota', 'provinsi', 'nasional', 'internasional']],
        // sama dengan enum prestasi.jenis_prestasi
        'jenis_prestasi' => ['label' => 'Pilihan: Jenis prestasi', 'nilai' => ['akademik', 'non-akademik']],
        // diambil dari tabel dosen
        'dosen' => ['label' => 'Pilihan: Dosen', 'nilai' => null],
    ];

    /** Tipe isian biasa (diketik / diunggah pengaju). */
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
        return array_merge(array_keys(self::BIASA), array_keys(self::DAFTAR));
    }

    /** Daftar nilai tetap untuk tipe baku, atau null (bukan tipe baku / tipe dosen). */
    public static function nilai(string $jenis): ?array
    {
        return self::DAFTAR[$jenis]['nilai'] ?? null;
    }

    /** [{nilai, label}] untuk dropdown tipe di form admin. */
    public static function daftarTipe(): array
    {
        $out = [];

        foreach (self::BIASA as $nilai => $label) {
            $out[] = ['nilai' => $nilai, 'label' => $label, 'baku' => false];
        }

        foreach (self::DAFTAR as $nilai => $info) {
            $out[] = ['nilai' => $nilai, 'label' => $info['label'], 'baku' => true];
        }

        return $out;
    }
}
