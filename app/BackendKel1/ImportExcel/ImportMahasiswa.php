<?php

namespace App\BackendKel1\ImportExcel;

use App\Repositories\MahasiswaStore;
use App\Support\NimMahasiswa;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * Impor mahasiswa dari Excel (admin). Memakai MahasiswaStore::create (ikut DATA_SOURCE) dan NimMahasiswa
 * seperti form tambah mahasiswa. Angkatan diambil dari NIM. Sandi awal = NIM (penggantian sandi oleh fitur login).
 * NIM atau email yang sudah terdaftar (atau berulang di berkas) DILEWATI dan dilaporkan.
 */
class ImportMahasiswa implements Impor
{
    public const MAKS_BARIS = 1000;

    private const KOLOM = [
        'nim' => ['nim'],
        'nama' => ['namalengkap', 'nama'],
        'email' => ['email', 'emailakun'],
        'status' => ['status', 'statusakademik'],
    ];

    public function __construct(private MahasiswaStore $mhs) {}

    public function kolomPratinjau(): array
    {
        return ['nim' => 'NIM', 'nama' => 'Nama', 'angkatan' => 'Angkatan', 'email' => 'Email', 'status' => 'Status'];
    }

    public function periksa(string $path): array
    {
        $baca = BacaExcel::baca($path, self::KOLOM, self::MAKS_BARIS);
        if (isset($baca['error'])) {
            return ['error' => $baca['error']];
        }

        $statusOk = $this->mhs->opsiStatus();
        $valid = $dilewati = $salah = [];
        $nimDiBerkas = $emailDiBerkas = [];

        foreach ($baca['rows'] as $baris => $r) {
            $pesan = [];

            $nim = BacaExcel::teks($r['nim']);
            $angkatan = null;
            if ($nim === '') {
                $pesan[] = 'NIM wajib diisi.';
            } elseif ($salahNim = NimMahasiswa::kesalahan($nim)) {
                $pesan[] = $salahNim;
            } else {
                $angkatan = NimMahasiswa::parse($nim)['tahun_masuk'] ?? null;
                if ($angkatan === null) {
                    $pesan[] = 'NIM tidak dapat dibaca.';
                }
            }

            $nama = BacaExcel::teks($r['nama']);
            if ($nama === '') {
                $pesan[] = 'Nama lengkap wajib diisi.';
            } elseif (mb_strlen($nama) > 100) {
                $pesan[] = 'Nama maksimal 100 karakter.';
            }

            $email = mb_strtolower(BacaExcel::teks($r['email']));
            if ($email === '' || mb_strlen($email) > 150 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $pesan[] = 'Email tidak valid.';
            }

            $status = null;
            $mentah = BacaExcel::teks($r['status']) ?: ($statusOk[0] ?? 'Aktif');
            foreach ($statusOk as $s) {
                if (mb_strtolower($s) === mb_strtolower($mentah)) {
                    $status = $s;
                }
            }
            if ($status === null) {
                $pesan[] = 'Status harus salah satu dari: '.implode(', ', $statusOk).'.';
            }

            if ($pesan) {
                $salah[] = ['baris' => $baris, 'kode' => $nim, 'nama' => $nama, 'pesan' => $pesan];

                continue;
            }

            $alasan = null;
            if (isset($nimDiBerkas[$nim])) {
                $alasan = 'NIM sama dengan baris '.$nimDiBerkas[$nim].' di berkas ini.';
            } elseif (isset($emailDiBerkas[$email])) {
                $alasan = 'Email sama dengan baris '.$emailDiBerkas[$email].' di berkas ini.';
            } elseif ($this->mhs->usedByOther('nim', $nim, '')) {
                $alasan = 'NIM sudah terdaftar.';
            } elseif ($this->mhs->usedByOther('email', $email, '')) {
                $alasan = 'Email sudah dipakai akun lain.';
            }
            if ($alasan) {
                $dilewati[] = ['baris' => $baris, 'kode' => $nim, 'nama' => $nama, 'alasan' => $alasan];

                continue;
            }

            $nimDiBerkas[$nim] = $emailDiBerkas[$email] = $baris;
            $valid[] = compact('baris', 'nim', 'nama', 'angkatan', 'email', 'status');
        }

        return ['total' => count($baca['rows']), 'valid' => $valid, 'dilewati' => $dilewati, 'salah' => $salah];
    }

    public function simpan(array $valid): array
    {
        $tersimpan = $lewat = 0;
        $gagal = [];

        foreach ($valid as $v) {
            if ($this->mhs->usedByOther('nim', $v['nim'], '') || $this->mhs->usedByOther('email', $v['email'], '')) {
                $lewat++;

                continue;
            }
            try {
                $this->mhs->create([
                    'nim' => $v['nim'],
                    'nama' => $v['nama'],
                    'angkatan' => $v['angkatan'],
                    'status' => $v['status'],
                    'email' => $v['email'],
                    'password' => $v['nim'],   // sandi awal = NIM
                ]);
                $tersimpan++;
            } catch (Throwable $e) {
                report($e);
                $gagal[] = ['baris' => $v['baris'], 'kode' => $v['nim'], 'alasan' => 'Gagal disimpan ke penyimpanan data.'];
            }
        }

        return ['tersimpan' => $tersimpan, 'dilewati' => $lewat, 'gagal' => $gagal];
    }

    public function template(): Spreadsheet
    {
        return TemplateExcel::buat(
            ['NIM', 'Nama Lengkap', 'Email', 'Status'],
            [18, 32, 34, 14],
            ['A'],
            ['D' => $this->mhs->opsiStatus()],
            [
                'PETUNJUK IMPOR MAHASISWA',
                '',
                '1. Isi sheet "Data" mulai baris 2. Jangan mengubah baris judul (baris 1).',
                '2. NIM berformat jenjang.prodi.tahun.kelas.absen, contoh 4.33.25.1.09 (kolom sudah berformat teks). Angkatan diambil dari NIM. Sandi awal akun = NIM.',
                '3. Email: harus unik. Status: pilih dari daftar (kosong = '.($this->mhs->opsiStatus()[0] ?? 'Aktif').').',
                '4. NIM atau email yang sudah terdaftar akan dilewati, bukan ditimpa.',
                '5. Maksimal '.number_format(self::MAKS_BARIS, 0, ',', '.').' baris per berkas. Format berkas: .xlsx.',
            ],
            [['4.33.25.1.09', 'Rina Wulandari', 'rina.wulandari@mhs.polines.ac.id', $this->mhs->opsiStatus()[0] ?? 'Aktif']],
            self::MAKS_BARIS,
        );
    }
}
