<?php

namespace App\BackendKel1\ImportExcel;

use App\Repositories\MataKuliahStore;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Throwable;

/**
 * Impor mata kuliah dari Excel untuk satu kurikulum (kurikulum dipilih di halaman, bukan di Excel).
 * Memakai MataKuliahStore sehingga ikut DATA_SOURCE (json atau database) seperti form tambah manual.
 *
 * Aturan baris (sama dengan form tambah): semester 1-8, kode maks 30 karakter, nama maks 150 karakter,
 * SKS dan jam berupa angka bulat 0-99. Kode yang sudah ada di kurikulum itu (atau berulang di berkas) DILEWATI dan dilaporkan.
 */
class ImportMataKuliah
{
    public const MAKS_BARIS = 1000;

    /** Judul yang diterima untuk tiap kolom (sudah dinormalkan oleh BacaExcel::normal). Yang pertama = judul di template. */
    private const KOLOM = [
        'semester' => ['semester', 'smt'],
        'kode' => ['kodemk', 'kode', 'kodematakuliah'],
        'nama' => ['namamk', 'nama', 'namamatakuliah'],
        'sks_teori' => ['sksteori'],
        'sks_praktek' => ['skspraktik', 'skspraktek'],
        'jam_teori' => ['jamteori'],
        'jam_praktek' => ['jampraktik', 'jampraktek'],
    ];

    private const ANGKA = [
        'sks_teori' => 'SKS Teori',
        'sks_praktek' => 'SKS Praktik',
        'jam_teori' => 'Jam Teori',
        'jam_praktek' => 'Jam Praktik',
    ];

    public function __construct(private MataKuliahStore $mk) {}

    /**
     * Periksa berkas tanpa menyimpan apa pun.
     *
     * @return array{error: string}|array{total: int, valid: array<int, array<string, mixed>>, dilewati: array<int, array<string, mixed>>, salah: array<int, array<string, mixed>>}
     */
    public function periksa(string $kurikulumId, string $path): array
    {
        $baca = BacaExcel::baca($path, self::KOLOM, self::MAKS_BARIS);
        if (isset($baca['error'])) {
            return ['error' => $baca['error']];
        }

        $valid = [];
        $dilewati = [];
        $salah = [];
        $kodeDiBerkas = [];

        foreach ($baca['rows'] as $baris => $r) {
            $pesan = [];

            $semester = BacaExcel::bulat($r['semester']);
            if ($semester === null || $semester < 1 || $semester > 8) {
                $pesan[] = 'Semester harus angka 1 sampai 8.';
            }

            $kode = BacaExcel::teks($r['kode']);
            if ($kode === '') {
                $pesan[] = 'Kode MK wajib diisi.';
            } elseif (mb_strlen($kode) > 30) {
                $pesan[] = 'Kode MK maksimal 30 karakter.';
            }

            $nama = BacaExcel::teks($r['nama']);
            if ($nama === '') {
                $pesan[] = 'Nama MK wajib diisi.';
            } elseif (mb_strlen($nama) > 150) {
                $pesan[] = 'Nama MK maksimal 150 karakter.';
            }

            $angka = [];
            foreach (self::ANGKA as $kunci => $label) {
                $nilai = BacaExcel::bulat($r[$kunci]);
                if ($nilai === null || $nilai < 0 || $nilai > 99) {
                    $pesan[] = $label.' harus angka bulat 0 sampai 99.';
                }
                $angka[$kunci] = $nilai;
            }

            if ($pesan) {
                $salah[] = ['baris' => $baris, 'kode' => $kode, 'nama' => $nama, 'pesan' => $pesan];

                continue;
            }

            $kunciKode = mb_strtolower($kode);
            if (isset($kodeDiBerkas[$kunciKode])) {
                $dilewati[] = ['baris' => $baris, 'kode' => $kode, 'nama' => $nama, 'alasan' => 'Kode sama dengan baris '.$kodeDiBerkas[$kunciKode].' di berkas ini.'];

                continue;
            }
            if ($this->mk->kodeExists($kurikulumId, $kode)) {
                $dilewati[] = ['baris' => $baris, 'kode' => $kode, 'nama' => $nama, 'alasan' => 'Kode sudah ada di kurikulum ini.'];

                continue;
            }

            $kodeDiBerkas[$kunciKode] = $baris;
            $valid[] = ['baris' => $baris, 'semester' => $semester, 'kode' => $kode, 'nama' => $nama] + $angka;
        }

        return ['total' => count($baca['rows']), 'valid' => $valid, 'dilewati' => $dilewati, 'salah' => $salah];
    }

    /**
     * Simpan baris valid dalam satu transaksi. Kode yang ternyata sudah ada (mis. dibuat orang lain sejak pratinjau) dilewati.
     *
     * @param  array<int, array<string, mixed>>  $valid
     * @return int jumlah baris yang tersimpan
     */
    public function simpan(string $kurikulumId, array $valid): int
    {
        return DB::transaction(function () use ($kurikulumId, $valid) {
            $tersimpan = 0;

            foreach ($valid as $v) {
                if ($this->mk->kodeExists($kurikulumId, $v['kode'])) {
                    continue;
                }
                $this->mk->create([
                    'kurikulum' => $kurikulumId,
                    'semester' => $v['semester'],
                    'kode' => $v['kode'],
                    'nama' => $v['nama'],
                    'sks_teori' => $v['sks_teori'],
                    'sks_praktek' => $v['sks_praktek'],
                    'jam_teori' => $v['jam_teori'],
                    'jam_praktek' => $v['jam_praktek'],
                ]);
                $tersimpan++;
            }

            return $tersimpan;
        });
    }

    /** Template Excel: sheet "Data" (hanya judul kolom) dan sheet "Petunjuk" (aturan dan contoh). */
    public function template(): Spreadsheet
    {
        $buku = new Spreadsheet;

        $data = $buku->getActiveSheet();
        $data->setTitle('Data');
        $data->fromArray(['Semester', 'Kode MK', 'Nama MK', 'SKS Teori', 'SKS Praktik', 'Jam Teori', 'Jam Praktik'], null, 'A1');
        $data->getStyle('A1:G1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $data->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2277FF');
        $data->freezePane('A2');
        foreach (['A' => 12, 'B' => 18, 'C' => 46, 'D' => 12, 'E' => 14, 'F' => 12, 'G' => 14] as $kolom => $lebar) {
            $data->getColumnDimension($kolom)->setWidth($lebar);
        }
        $data->getStyle('B2:B'.(self::MAKS_BARIS + 1))->getNumberFormat()->setFormatCode('@');   // kode tetap teks

        // Pembatas isian (pelengkap; kalau gagal dipasang, impor tetap memeriksa sendiri).
        try {
            $this->pembatas($data, 'A2:A'.(self::MAKS_BARIS + 1), 1, 8, 'Semester harus angka 1 sampai 8.');
            $this->pembatas($data, 'D2:G'.(self::MAKS_BARIS + 1), 0, 99, 'Isi dengan angka bulat 0 sampai 99.');
        } catch (Throwable) {
        }

        $petunjuk = $buku->createSheet();
        $petunjuk->setTitle('Petunjuk');
        $baris = [
            ['PETUNJUK IMPOR MATA KULIAH'],
            [''],
            ['1. Isi sheet "Data" mulai baris 2. Jangan mengubah, menghapus, atau memindah baris judul (baris 1).'],
            ['2. Kurikulum dipilih di halaman impor, bukan di Excel. Satu berkas untuk satu kurikulum.'],
            ['3. Semester: angka 1 sampai 8.'],
            ['4. Kode MK: maksimal 30 karakter, harus unik di dalam kurikulum.'],
            ['5. SKS dan Jam: angka bulat 0 sampai 99.'],
            ['6. Kode MK yang sudah ada di kurikulum itu akan dilewati, bukan ditimpa.'],
            ['7. Maksimal '.number_format(self::MAKS_BARIS, 0, ',', '.').' baris per berkas. Format berkas: .xlsx.'],
            [''],
            ['CONTOH ISI (jangan disalin bila bukan data sebenarnya)'],
            ['Semester', 'Kode MK', 'Nama MK', 'SKS Teori', 'SKS Praktik', 'Jam Teori', 'Jam Praktik'],
            [1, '432-191-101', 'Pengantar Teknologi Informasi', 2, 1, 2, 3],
            [2, '432-191-201', 'Pemrograman Dasar', 2, 2, 2, 4],
        ];
        $petunjuk->fromArray($baris, null, 'A1');
        $petunjuk->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $petunjuk->getStyle('A11')->getFont()->setBold(true);
        $petunjuk->getStyle('A12:G12')->getFont()->setBold(true);
        $petunjuk->getColumnDimension('A')->setWidth(14);
        $petunjuk->getColumnDimension('B')->setWidth(18);
        $petunjuk->getColumnDimension('C')->setWidth(34);

        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    private function pembatas($sheet, string $rentang, int $min, int $maks, string $pesan): void
    {
        $v = new DataValidation;
        $v->setType(DataValidation::TYPE_WHOLE);
        $v->setOperator(DataValidation::OPERATOR_BETWEEN);
        $v->setFormula1((string) $min);
        $v->setFormula2((string) $maks);
        $v->setAllowBlank(true);
        $v->setShowErrorMessage(true);
        $v->setErrorTitle('Isian tidak valid');
        $v->setError($pesan);
        $sheet->setDataValidation($rentang, $v);
    }
}
