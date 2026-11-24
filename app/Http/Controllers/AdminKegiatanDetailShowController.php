<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Carbon\Carbon;

/**
 * Detail satu kegiatan (baca saja) untuk SEMUA jenis: dosen, mahasiswa, prodi.
 * Menggantikan AdminKegiatanDosenDetailShowController dan AdminKegiatanMahasiswaDetailShowController.
 * Jenis datang dari route (->defaults('jenis', ...)), data dari API GET /admin/kegiatan/{id}.
 */
class AdminKegiatanDetailShowController extends Controller
{
    private const JENIS = [
        'dosen' => ['label' => 'Kegiatan dosen', 'tab' => 'Kegiatan Dosen', 'rute' => 'admin.kegiatan.dosen', 'pengaju' => 'Pengaju'],
        'mahasiswa' => ['label' => 'Kegiatan mahasiswa', 'tab' => 'Kegiatan Mahasiswa', 'rute' => 'admin.kegiatan.mahasiswa', 'pengaju' => 'Pengaju'],
        'prodi' => ['label' => 'Kegiatan prodi', 'tab' => 'Kegiatan Prodi', 'rute' => null, 'pengaju' => 'Penanggung jawab'],
    ];

    public function show(string $id, string $jenis)
    {
        abort_unless(isset(self::JENIS[$jenis]), 404, 'Kegiatan tidak ditemukan.');

        try {
            $hasil = ApiClient::pengguna()->get('/admin/kegiatan/'.rawurlencode($id));
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 404) {
                abort(404, 'Kegiatan tidak ditemukan.');
            }
            if (in_array($e->getStatusCode(), [401, 403], true)) {
                throw $e;
            }
            \Log::error('Gagal memuat detail kegiatan: '.$e->getMessage().' (Code: '.$e->getStatusCode().')');

            return redirect()->route('admin.kegiatan.index', ['tab' => $jenis])
                ->with('error', 'Gagal mengambil data dari API: '.$e->getMessage());
        }

        $row = is_array($hasil['data'] ?? null) ? $hasil['data'] : [];

        // Jenis di URL harus sama dengan jenis kegiatannya (mencegah /dosen/{id kegiatan mahasiswa}).
        abort_if($row === [] || (isset($row['jenis']) && $row['jenis'] !== $jenis), 404, 'Kegiatan tidak ditemukan.');

        $d = $this->susunDetail($row, $jenis);

        return view('admin.kegiatan-detail', compact('d'));
    }

    private function teks(array $row, string $kunci, string $default = '-'): string
    {
        $v = $row[$kunci] ?? null;

        return is_scalar($v) && trim((string) $v) !== '' ? (string) $v : $default;
    }

    /** "tanggal_mulai" -> "Tanggal mulai" */
    private function labelDari(string $input): string
    {
        return ucfirst(str_replace('_', ' ', trim($input)));
    }

    private function tanggalSk(?string $iso): string
    {
        if (! $iso) {
            return '-';
        }
        try {
            return Carbon::parse($iso)->locale('id')->translatedFormat('j F Y');
        } catch (\Throwable) {
            return $iso;
        }
    }

    private function susunDetail(array $row, string $jenis): array
    {
        $cfg = self::JENIS[$jenis];
        $pengaju = $this->teks($row, 'pengaju');
        $tanggal = $this->teks($row, 'tanggal');
        $status = $this->teks($row, 'status', 'Disetujui');

        // Isian dinamis (dari kategori): [{input, isian}] -> [label => nilai]. Nilai ganda pada input yang sama digabung.
        $isian = [];
        foreach ((array) ($row['isian'] ?? []) as $i) {
            if (! is_array($i) || ! isset($i['input'])) {
                continue;
            }
            $label = $this->labelDari((string) $i['input']);
            $nilai = is_scalar($i['isian'] ?? null) ? trim((string) $i['isian']) : '';
            if ($nilai === '') {
                continue;
            }
            $isian[$label] = isset($isian[$label]) ? $isian[$label].', '.$nilai : $nilai;
        }

        // "penyelenggara" jadi baris tetap; sisanya informasi tambahan.
        $penyelenggara = $isian['Penyelenggara'] ?? '-';
        unset($isian['Penyelenggara']);

        $info = [
            $cfg['pengaju'] => $pengaju,
            'Kategori' => $this->teks($row, 'kategori'),
            'Jenis' => $cfg['label'],
            'Penyelenggara' => $penyelenggara,
            'Tanggal' => $tanggal,
        ];
        if (! empty($row['nomor_sk'])) {
            $info['Nomor SK'] = (string) $row['nomor_sk'];
        }
        if (! empty($row['tanggal_sk'])) {
            $info['Tanggal SK'] = $this->tanggalSk((string) $row['tanggal_sk']);
        }
        $info += $isian;

        // Anggota: [{nama, status}]. Dokumen: [{nama, kategori, url}]. Riwayat: teks atau {status, tanggal, keterangan}.
        $anggota = [];
        foreach ((array) ($row['anggota'] ?? []) as $a) {
            if (is_array($a)) {
                $anggota[] = ['nama' => $a['nama'] ?? '-', 'status' => $a['status'] ?? $a['peran'] ?? '-'];
            }
        }

        $dokumen = [];
        foreach ((array) ($row['dokumen'] ?? []) as $x) {
            if (is_array($x)) {
                $dokumen[] = [
                    'nama' => $x['nama'] ?? $x['nama_dokumen'] ?? '-',
                    'kategori' => $x['kategori'] ?? $x['jenis_dokumen'] ?? '-',
                    'url' => $x['url'] ?? null,
                ];
            }
        }

        $riwayat = array_map(function ($r) {
            if (is_array($r)) {
                return trim(implode(' — ', array_filter([
                    $r['status'] ?? null, $r['tanggal'] ?? $r['waktu'] ?? null, $r['keterangan'] ?? $r['catatan'] ?? null,
                ])));
            }

            return (string) $r;
        }, (array) ($row['riwayat'] ?? []));

        return [
            'judul' => $this->teks($row, 'nama'),
            'status' => $status,
            'jenis' => $jenis,
            'tab_label' => $cfg['tab'],
            'rute_daftar' => $cfg['rute'],
            'info' => $info,
            'anggota' => $anggota ?: [['nama' => $pengaju, 'status' => $jenis === 'prodi' ? 'Penanggung jawab' : 'Pengaju']],
            'dokumen' => $dokumen,
            'riwayat' => $riwayat ?: ["Diajukan oleh {$pengaju}, {$tanggal}", "Status saat ini: {$status}"],
        ];
    }
}