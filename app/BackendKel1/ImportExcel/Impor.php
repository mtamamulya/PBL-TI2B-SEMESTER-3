<?php

namespace App\BackendKel1\ImportExcel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Kontrak impor data (dosen, mahasiswa) yang dipakai ImportDataController.
 * Bentuk hasil periksa(): total, valid[], dilewati[] (baris, kode, nama, alasan), salah[] (baris, kode, nama, pesan[]).
 * Tiap baris valid memuat 'baris' ditambah kunci-kunci yang tercantum di kolomPratinjau().
 */
interface Impor
{
    public function periksa(string $path): array;

    /** @return array{tersimpan: int, dilewati: int, gagal: array<int, array{baris: int, kode: string, alasan: string}>} */
    public function simpan(array $valid): array;

    public function template(): Spreadsheet;

    /** @return array<string, string> kunci => judul kolom pada tabel pratinjau */
    public function kolomPratinjau(): array;
}
