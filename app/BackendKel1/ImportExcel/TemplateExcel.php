<?php

namespace App\BackendKel1\ImportExcel;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Throwable;

/** Pembuat template Excel sederhana: sheet "Data" (judul saja) dan sheet "Petunjuk". */
class TemplateExcel
{
    /**
     * @param  array<int, string>  $judul
     * @param  array<int, int>  $lebar  lebar tiap kolom
     * @param  array<int, string>  $teksKolom  huruf kolom yang dipaksa berformat teks (mis. NIP, NIM)
     * @param  array<string, array<int, string>>  $daftar  huruf kolom => pilihan dropdown
     * @param  array<int, string>  $petunjuk  baris petunjuk
     * @param  array<int, array<int, mixed>>  $contoh
     */
    public static function buat(array $judul, array $lebar, array $teksKolom, array $daftar, array $petunjuk, array $contoh, int $maksBaris): Spreadsheet
    {
        $buku = new Spreadsheet;
        $data = $buku->getActiveSheet();
        $data->setTitle('Data');
        $data->fromArray($judul, null, 'A1');
        $akhir = chr(64 + count($judul));
        $data->getStyle("A1:{$akhir}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $data->getStyle("A1:{$akhir}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2277FF');
        $data->freezePane('A2');
        foreach ($lebar as $i => $w) {
            $data->getColumnDimension(chr(65 + $i))->setWidth($w);
        }
        foreach ($teksKolom as $k) {
            $data->getStyle("{$k}2:{$k}".($maksBaris + 1))->getNumberFormat()->setFormatCode('@');
        }
        try {
            foreach ($daftar as $k => $pilihan) {
                $v = new DataValidation;
                $v->setType(DataValidation::TYPE_LIST);
                $v->setAllowBlank(true);
                $v->setShowDropDown(true);
                $v->setShowErrorMessage(true);
                $v->setErrorTitle('Pilihan tidak valid');
                $v->setError('Pilih salah satu dari daftar.');
                $v->setFormula1('"'.implode(',', $pilihan).'"');
                $data->setDataValidation("{$k}2:{$k}".($maksBaris + 1), $v);
            }
        } catch (Throwable) {
        }

        $p = $buku->createSheet();
        $p->setTitle('Petunjuk');
        $baris = array_map(fn ($t) => [$t], $petunjuk);
        $baris[] = [''];
        $baris[] = ['CONTOH ISI (data karangan, jangan diimpor apa adanya)'];
        $baris[] = $judul;
        foreach ($contoh as $c) {
            $baris[] = $c;
        }
        $p->fromArray($baris, null, 'A1');
        $p->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $nContoh = count($petunjuk) + 2;
        $p->getStyle('A'.($nContoh + 1))->getFont()->setBold(true);
        $p->getStyle('A'.($nContoh + 2).':'.$akhir.($nContoh + 2))->getFont()->setBold(true);
        foreach ($lebar as $i => $w) {
            $p->getColumnDimension(chr(65 + $i))->setWidth(max($w, 16));
        }
        $buku->setActiveSheetIndex(0);

        return $buku;
    }
}
