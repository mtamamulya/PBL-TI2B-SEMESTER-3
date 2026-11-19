<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Carbon\Carbon;

class KaprodiKegiatanMahasiswaDetailShowController extends Controller
{
    /** Detail satu kegiatan mahasiswa (baca saja), dari API GET /admin/kegiatan/{id}. */
    public function show(string $id)
    {
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

            return redirect()->route('kaprodi.kegiatan.index', ['tab' => 'mahasiswa'])->with('error', 'Gagal mengambil data dari API: '.$e->getMessage());
        }

        $row = is_array($hasil['data'] ?? null) ? $hasil['data'] : [];

        // Halaman ini khusus kegiatan mahasiswa; jenis lain dianggap tidak ditemukan.
        abort_if($row === [] || (isset($row['jenis']) && $row['jenis'] !== 'mahasiswa'), 404, 'Kegiatan tidak ditemukan.');

        $d = $this->susunDetail($row);

        return view()->first([
            'kaprodi.kaprodi-kegiatan-mahasiswa-detail-show',
            'kaprodi.kegiatan-mahasiswa-detail',
        ], compact('d'));
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

    private function susunDetail(array $row): array
    {
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

        // "penyelenggara" dipakai baris khusus di view; sisanya jadi informasi tambahan.
        $penyelenggara = $isian['Penyelenggara'] ?? '-';
        unset($isian['Penyelenggara']);

        $tambahan = [];
        if (! empty($row['nomor_sk'])) {
            $tambahan['Nomor SK'] = (string) $row['nomor_sk'];
        }
        if (! empty($row['tanggal_sk'])) {
            $tambahan['Tanggal SK'] = $this->tanggalSk((string) $row['tanggal_sk']);
        }
        $tambahan += $isian;

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
            'pengaju' => $pengaju,
            'kategori' => $this->teks($row, 'kategori'),
            'jenis' => 'Kegiatan mahasiswa',
            'penyelenggara' => $penyelenggara,
            'tanggal' => $tanggal,
            'tambahan' => $tambahan,
            'anggota' => $anggota ?: [['nama' => $pengaju, 'status' => 'Pengaju']],
            'dokumen' => $dokumen,
            'riwayat' => $riwayat ?: ["Diajukan oleh {$pengaju}, {$tanggal}", "Status saat ini: {$status}"],
        ];
    }
}