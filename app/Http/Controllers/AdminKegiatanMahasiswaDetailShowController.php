<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;

class AdminKegiatanMahasiswaDetailShowController extends Controller
{
    /** Detail satu kegiatan mahasiswa (baca saja), dari API GET /admin/kegiatan/{id}. */
    public function show(string $id)
    {
        try {
            $hasil = ApiClient::pengguna()->get('/admin/kegiatan/'.rawurlencode($id));
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                abort(404, 'Kegiatan tidak ditemukan.');
            }
            if (in_array($e->getCode(), [401, 403], true)) {
                throw $e;
            }
            \Log::error('Gagal memuat detail kegiatan: '.$e->getMessage().' (Code: '.$e->getCode().')');

            return redirect()->route('admin.kegiatan.mahasiswa')->with('error', 'Gagal mengambil data dari API: '.$e->getMessage());
        }

        $row = is_array($hasil['data'] ?? null) ? $hasil['data'] : [];

        // Halaman ini khusus kegiatan mahasiswa; jenis lain dianggap tidak ditemukan.
        abort_if($row === [] || (isset($row['jenis']) && $row['jenis'] !== 'mahasiswa'), 404, 'Kegiatan tidak ditemukan.');

        $d = $this->susunDetail($row);

        return view()->first([
            'admin.kegiatan-mahasiswa-detail-show',
            'admin.kegiatan-mahasiswa-detail',
        ], compact('d'));
    }

    /** Nilai pertama yang ada dari daftar kemungkinan nama kolom. */
    private function ambil(array $row, array $keys, $default = '-')
    {
        foreach ($keys as $k) {
            if (isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null) {
                return $row[$k];
            }
        }

        return $default;
    }

    private function susunDetail(array $row): array
    {
        $judul = $this->ambil($row, ['nama', 'nama_kegiatan', 'kegiatan', 'judul']);
        $pengaju = $this->ambil($row, ['pengaju', 'mahasiswa', 'nama_mahasiswa']);
        $tanggal = $this->ambil($row, ['tanggal', 'tgl', 'date']);
        $status = $this->ambil($row, ['status'], 'Disetujui');

        // Riwayat: elemen boleh berupa teks atau objek {status, tanggal, keterangan}.
        $riwayat = array_map(function ($r) {
            if (is_array($r)) {
                return trim(implode(' — ', array_filter([
                    $r['status'] ?? $r['nama_status'] ?? null,
                    $r['tanggal'] ?? $r['waktu'] ?? null,
                    $r['keterangan'] ?? $r['catatan'] ?? null,
                ])));
            }

            return (string) $r;
        }, (array) ($row['riwayat'] ?? $row['timeline'] ?? []));

        if ($riwayat === []) {
            $riwayat = ["Diajukan oleh {$pengaju}, {$tanggal}", "Status saat ini: {$status}"];
        }

        $anggota = array_values(array_filter((array) ($row['anggota'] ?? []), 'is_array'));
        $dokumen = array_values(array_filter((array) ($row['dokumen'] ?? []), 'is_array'));

        return [
            'judul' => $judul,
            'status' => $status,
            'pengaju' => $pengaju,
            'kategori' => $this->ambil($row, ['kategori', 'nama_kategori']),
            'jenis' => 'Kegiatan mahasiswa',
            'penyelenggara' => $this->ambil($row, ['penyelenggara', 'organizer']),
            'tanggal' => $tanggal,
            'anggota' => $anggota ?: [['nama' => $pengaju, 'status' => 'Pengaju']],
            'dokumen' => array_map(fn ($x) => [
                'nama' => $x['nama'] ?? $x['nama_dokumen'] ?? '-',
                'kategori' => $x['kategori'] ?? $x['jenis_dokumen'] ?? '-',
                'url' => $x['url'] ?? null,
            ], $dokumen),
            'riwayat' => $riwayat,
        ];
    }
}