<?php

namespace App\BackendKel1\ImportExcel;

use App\Repositories\DosenStore;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * Impor dosen dari Excel (admin). Memakai DosenStore::create sehingga ikut DATA_SOURCE (json/database)
 * dan aturan yang sama dengan form tambah dosen. Sandi awal = NIP (penggantian sandi ditangani fitur login).
 * NIP atau email yang sudah terdaftar (atau berulang di berkas) DILEWATI dan dilaporkan.
 */
class ImportDosen implements Impor
{
    public const MAKS_BARIS = 1000;

    private const KOLOM = [
        'nama' => ['namalengkap', 'nama'],
        'nip' => ['nip'],
        'email' => ['email', 'emailakun'],
        'role' => ['role', 'peran'],
        'status' => ['status', 'statusdosen'],
        'pangkat' => ['pangkat', 'jabatan'],
        'golongan' => ['golongan'],
        'sk' => ['nomorsk', 'nosk'],
        'tanggal' => ['tanggalsk', 'tglsk'],
    ];

    public function __construct(private DosenStore $dosen) {}

    public function kolomPratinjau(): array
    {
        return ['nip' => 'NIP', 'nama' => 'Nama', 'email' => 'Email', 'role' => 'Role', 'status' => 'Status', 'pangkat' => 'Pangkat', 'golongan' => 'Golongan', 'sk' => 'No. SK', 'tanggal' => 'Tgl SK'];
    }

    public function periksa(string $path): array
    {
        $baca = BacaExcel::baca($path, self::KOLOM, self::MAKS_BARIS);
        if (isset($baca['error'])) {
            return ['error' => $baca['error']];
        }

        $pangkatOk = $this->dosen->opsiPangkat();
        $golonganOk = $this->dosen->opsiGolongan();
        $valid = $dilewati = $salah = [];
        $nipDiBerkas = $emailDiBerkas = [];

        foreach ($baca['rows'] as $baris => $r) {
            $pesan = [];

            $nama = BacaExcel::teks($r['nama']);
            if ($nama === '') {
                $pesan[] = 'Nama lengkap wajib diisi.';
            } elseif (mb_strlen($nama) > 100) {
                $pesan[] = 'Nama maksimal 100 karakter.';
            }

            $nip = BacaExcel::teks($r['nip']);
            if (! preg_match('/^\d{8,20}$/', $nip)) {
                $pesan[] = 'NIP harus berupa angka 8-20 digit.';
            }

            $email = mb_strtolower(BacaExcel::teks($r['email']));
            if ($email === '' || mb_strlen($email) > 150 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $pesan[] = 'Email tidak valid.';
            }

            $role = $this->cocok(BacaExcel::teks($r['role']) ?: 'dosen', array_keys(DosenStore::ROLE));
            if ($role === null) {
                $pesan[] = 'Role harus salah satu dari: '.implode(', ', array_keys(DosenStore::ROLE)).'.';
            }

            $status = $this->cocok(BacaExcel::teks($r['status']) ?: 'aktif', ['aktif', 'nonaktif']);
            if ($status === null) {
                $pesan[] = 'Status harus aktif atau nonaktif.';
            }

            $pangkat = $this->cocok(BacaExcel::teks($r['pangkat']), $pangkatOk);
            if ($pangkat === null) {
                $pesan[] = 'Pangkat harus salah satu dari: '.implode(', ', $pangkatOk).'.';
            }

            $golongan = $this->cocok(str_replace(' ', '', BacaExcel::teks($r['golongan'])), $golonganOk);
            if ($golongan === null) {
                $pesan[] = 'Golongan harus salah satu dari: '.implode(', ', $golonganOk).'.';
            }

            $sk = BacaExcel::teks($r['sk']);
            if ($sk === '' || mb_strlen($sk) > 100) {
                $pesan[] = 'Nomor SK wajib diisi (maksimal 100 karakter).';
            }

            $tanggal = BacaExcel::tanggal($r['tanggal']);
            if ($tanggal === null) {
                $pesan[] = 'Tanggal SK tidak valid (gunakan format tanggal Excel atau 2022-01-12).';
            }

            if ($pesan) {
                $salah[] = ['baris' => $baris, 'kode' => $nip, 'nama' => $nama, 'pesan' => $pesan];

                continue;
            }

            $alasan = null;
            if (isset($nipDiBerkas[$nip])) {
                $alasan = 'NIP sama dengan baris '.$nipDiBerkas[$nip].' di berkas ini.';
            } elseif (isset($emailDiBerkas[$email])) {
                $alasan = 'Email sama dengan baris '.$emailDiBerkas[$email].' di berkas ini.';
            } elseif ($this->dosen->exists('nip', $nip)) {
                $alasan = 'NIP sudah terdaftar.';
            } elseif ($this->dosen->exists('email', $email)) {
                $alasan = 'Email sudah dipakai akun lain.';
            }
            if ($alasan) {
                $dilewati[] = ['baris' => $baris, 'kode' => $nip, 'nama' => $nama, 'alasan' => $alasan];

                continue;
            }

            $nipDiBerkas[$nip] = $emailDiBerkas[$email] = $baris;
            $valid[] = compact('baris', 'nip', 'nama', 'email', 'role', 'status', 'pangkat', 'golongan', 'sk', 'tanggal');
        }

        return ['total' => count($baca['rows']), 'valid' => $valid, 'dilewati' => $dilewati, 'salah' => $salah];
    }

    public function simpan(array $valid): array
    {
        $tersimpan = $lewat = 0;
        $gagal = [];

        foreach ($valid as $v) {
            if ($this->dosen->exists('nip', $v['nip']) || $this->dosen->exists('email', $v['email'])) {
                $lewat++;

                continue;
            }
            try {
                $this->dosen->create([
                    'nama' => $v['nama'],
                    'nip' => $v['nip'],
                    'status' => $v['status'],
                    'email' => $v['email'],
                    'role' => $v['role'],
                    'password' => $v['nip'],   // sandi awal = NIP
                    'foto' => null,
                    'pangkat' => $v['pangkat'],
                    'golongan' => $v['golongan'],
                    'sk' => $v['sk'],
                    'tanggal' => $v['tanggal'],
                ]);
                $tersimpan++;
            } catch (Throwable $e) {
                report($e);
                $gagal[] = ['baris' => $v['baris'], 'kode' => $v['nip'], 'alasan' => 'Gagal disimpan ke penyimpanan data.'];
            }
        }

        return ['tersimpan' => $tersimpan, 'dilewati' => $lewat, 'gagal' => $gagal];
    }

    public function template(): Spreadsheet
    {
        return TemplateExcel::buat(
            ['Nama Lengkap', 'NIP', 'Email', 'Role', 'Status', 'Pangkat', 'Golongan', 'Nomor SK', 'Tanggal SK'],
            [30, 22, 30, 12, 12, 16, 12, 24, 14],
            ['B'],
            [
                'D' => array_keys(DosenStore::ROLE),
                'E' => ['aktif', 'nonaktif'],
                'F' => $this->dosen->opsiPangkat(),
                'G' => $this->dosen->opsiGolongan(),
            ],
            [
                'PETUNJUK IMPOR DOSEN',
                '',
                '1. Isi sheet "Data" mulai baris 2. Jangan mengubah baris judul (baris 1).',
                '2. NIP: angka 8-20 digit (kolom sudah berformat teks). Sandi awal akun = NIP.',
                '3. Email: harus unik. Role: dosen atau kaprodi. Status: aktif atau nonaktif (kosong = aktif).',
                '4. Pangkat dan Golongan: pilih dari daftar. Nomor SK wajib. Tanggal SK berformat tanggal Excel atau 2022-01-12.',
                '5. NIP atau email yang sudah terdaftar akan dilewati, bukan ditimpa.',
                '6. Maksimal '.number_format(self::MAKS_BARIS, 0, ',', '.').' baris per berkas. Format berkas: .xlsx.',
            ],
            [['Budi Santoso', '198501012010', 'budi.santoso@polines.ac.id', 'dosen', 'aktif', $this->dosen->opsiPangkat()[0] ?? 'Asisten Ahli', $this->dosen->opsiGolongan()[0] ?? 'III/a', '1042/UN/KP/2022', '2022-01-12']],
            self::MAKS_BARIS,
        );
    }

    /** Cocokkan tanpa memperhatikan huruf besar/kecil; hasilnya nilai resmi dari daftar, atau null. */
    private function cocok(string $nilai, array $daftar): ?string
    {
        foreach ($daftar as $d) {
            if (mb_strtolower((string) $d) === mb_strtolower(trim($nilai))) {
                return (string) $d;
            }
        }

        return null;
    }
}
