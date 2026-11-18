<?php

namespace App\Http\Controllers;

use App\Repositories\SuratPersetujuanStore;
use App\Repositories\TandaTanganStore;
use App\Support\PanggilApiWeb;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Pengajuan sisi KAPRODI terhubung ke Backend API.
 */
class KaprodiPengajuanController extends Controller
{
    use PanggilApiWeb;

    public function __construct(
        private SuratPersetujuanStore $surat,
        private TandaTanganStore $ttd,
    ) {}

    /** Daftar: semua pengajuan yang pernah dikirim admin ke kaprodi */
    public function index()
    {
        $hasil = $this->apiGet('/kaprodi/pengajuan', ['per_page' => 100]);
        $rows = array_map(fn (array $r) => $this->bentukBaris($r), $hasil['data'] ?? []);

        return view('kaprodi.pengajuan', ['rows' => $rows]);
    }

    /** Detail + pratinjau surat + tombol tanda tangan */
    public function show(string $id)
    {
        $p = $this->apiGet("/kaprodi/pengajuan/{$id}")['data'] ?? [];
        $snap = $this->surat->find($id);

        $status = $this->labelStatus($p['status'] ?? 'direview');
        $sudahTtd = $status === 'Disetujui' && ! empty($snap['tanda_tangan_path']);
        $bisaTtd = $status === 'Menunggu Tanda Tangan';
        $ttdAktif = $this->ttd->aktif(TandaTanganStore::kunci()) !== null;

        if ($sudahTtd) {
            $ttdUrl = route('kaprodi.pengajuan.ttd', $id).'?v='.strtotime((string) ($snap['ditandatangani_pada'] ?? 'now'));
        } else {
            $ttdUrl = $ttdAktif ? route('kaprodi.tanda-tangan.gambar') : null;
        }

        [$namaKaprodi, $nipKaprodi] = $this->identitasKaprodi($snap, $sudahTtd);

        $info = $this->susunInfo($p);
        $anggota = $this->susunAnggota($p);
        $dokumen = $this->susunDokumen($p);
        $riwayat = $this->susunRiwayat($p);

        $nomorSurat = $p['nomor_sk'] ?? null;
        $tanggalSurat = ! empty($p['tanggal_sk'])
            ? Carbon::parse($p['tanggal_sk'])->locale('id')->translatedFormat('j F Y')
            : null;

        $isiSurat = $this->buatIsiSurat(
            $p['template_surat'] ?? null,
            ['judul' => $p['nama_kegiatan'] ?? '-', 'info' => $info],
            (string) ($nomorSurat ?? '-'),
            $tanggalSurat
        );

        $lampiranDokumen = collect($p['dokumen'] ?? [])
            ->filter(fn (array $d) => (bool) ($d['is_lampiran'] ?? false))
            ->pluck('nama_file')
            ->values()
            ->all();

        return view('kaprodi.tinjau-pengajuan', [
            'id' => (string) ($p['id_kegiatan'] ?? $id),
            'judul' => $p['nama_kegiatan'] ?? '-',
            'status' => $status,
            'info' => $info,
            'anggota' => $anggota,
            'dokumen' => $dokumen,
            'riwayat' => $riwayat,
            'catatan' => $p['catatan_revisi'] ?? null,
            'nomorSurat' => $nomorSurat,
            'isiSurat' => $isiSurat,
            'tanggalSurat' => $tanggalSurat,
            'adaLampiran' => (bool) ($p['lampiran_peserta'] ?? false),
            'peserta' => $anggota,
            'lampiranDokumen' => $lampiranDokumen,
            'namaKaprodi' => $namaKaprodi,
            'nipKaprodi' => $nipKaprodi,
            'ttdUrl' => $ttdUrl,
            'ttdAktif' => $ttdAktif,
            'sudahTtd' => $sudahTtd,
            'bisaTtd' => $bisaTtd,
        ]);
    }

    /** Keputusan kaprodi: setujui (dengan tanda tangan), revisi, atau tolak */
    public function keputusan(Request $request, string $id)
    {
        $data = $request->validate([
            'aksi' => ['required', 'in:setujui,revisi,tolak'],
            'catatan' => ['nullable', 'string', 'max:1000', 'required_if:aksi,revisi,tolak'],
        ], [
            'catatan.required_if' => 'Catatan wajib diisi saat meminta revisi atau menolak.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        if ($data['aksi'] === 'setujui') {
            $salinan = $this->ttd->salinKeSurat(TandaTanganStore::kunci(), $id);
            if (! $salinan) {
                return back()->withErrors(['tanda_tangan' => 'Unggah tanda tangan terlebih dahulu.']);
            }

            $respons = $this->apiPost("/kaprodi/pengajuan/{$id}/keputusan", [
                'aksi' => 'setujui',
            ]);

            [$nama, $nip] = $this->identitasKaprodi(null, false);
            $this->surat->save($id, [
                'tanda_tangan_path' => $salinan,
                'ditandatangani_pada' => Carbon::now()->toIso8601String(),
                'kaprodi' => ['nama' => $nama, 'nip' => $nip],
            ]);

            return redirect()
                ->route('kaprodi.pengajuan.show', $id)
                ->with('success', $respons['message'] ?? 'Surat berhasil ditandatangani dan pengajuan disetujui.');
        }

        $respons = $this->apiPost("/kaprodi/pengajuan/{$id}/keputusan", [
            'aksi' => $data['aksi'],
            'catatan_revisi' => trim((string) ($data['catatan'] ?? '')),
        ]);

        $this->surat->delete($id);

        return redirect()
            ->route('kaprodi.pengajuan.show', $id)
            ->with('success', $respons['message'] ?? 'Keputusan Kaprodi berhasil disimpan.');
    }

    /** Gambar tanda tangan pada surat yang SUDAH ditandatangani */
    public function ttdSurat(string $id)
    {
        $this->apiGet("/kaprodi/pengajuan/{$id}");

        $snap = $this->surat->find($id);
        $path = $snap['tanda_tangan_path'] ?? null;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /* ---------- bantu ---------- */

    private function bentukBaris(array $r): array
    {
        $rawDate = $r['tanggal_mulai'] ?? ($r['tanggal_status'] ?? null);
        $tanggalIndo = '-';
        $semester = '';

        if ($rawDate) {
            $dt = Carbon::parse($rawDate);
            $tanggalIndo = $dt->locale('id')->translatedFormat('j F Y');
            $semester = ($dt->month >= 3 && $dt->month <= 8) ? 'Genap' : 'Ganjil';
        }

        return [
            'id' => $r['id_kegiatan'],
            'nama' => $r['nama_kegiatan'] ?? '-',
            'kategori' => $r['kategori'] ?? '-',
            'pengaju' => $r['pengaju']['nama'] ?? '-',
            'tanggal' => $tanggalIndo,
            'raw_date' => $rawDate,
            'status' => $this->labelStatus($r['status'] ?? 'direview'),
            'semester' => $semester,
            'jenis_pengaju' => $r['pengaju']['jenis_pengaju'] ?? 'Mahasiswa',
        ];
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'direview' => 'Menunggu Tanda Tangan',
            'diterima', 'selesai' => 'Disetujui',
            'direvisi' => 'Direvisi',
            'ditolak' => 'Ditolak',
            'diajukan' => 'Diajukan',
            default => ucfirst($status),
        };
    }

    private function susunInfo(array $p): array
    {
        $mulai = ! empty($p['tanggal_mulai'])
            ? Carbon::parse($p['tanggal_mulai'])->locale('id')->translatedFormat('j F Y')
            : '-';
        $selesai = ! empty($p['tanggal_selesai'])
            ? Carbon::parse($p['tanggal_selesai'])->locale('id')->translatedFormat('j F Y')
            : null;
        $tanggal = ($selesai && $selesai !== $mulai) ? "{$mulai} s.d. {$selesai}" : $mulai;

        $isianMap = [];
        foreach (($p['isian'] ?? []) as $item) {
            if (! empty($item['input'])) {
                $isianMap[strtolower($item['input'])] = $item['isian'] ?? '-';
            }
        }

        $rows = [
            ['Pengaju', $p['pengaju']['nama'] ?? '-'],
            ['NIM/NIP', $p['pengaju']['identitas'] ?? '-'],
            ['Kategori', $p['kategori'] ?? '-'],
            ['Jenis', $p['jenis'] ?? '-'],
            ['Penyelenggara', $isianMap['penyelenggara'] ?? '-'],
            ['Lokasi', $isianMap['lokasi'] ?? ($isianMap['penyelenggara'] ?? '-')],
            ['Tanggal', $tanggal],
        ];

        $sudahAda = ['penyelenggara', 'lokasi', 'tanggal_mulai', 'tanggal_selesai'];
        foreach (($p['isian'] ?? []) as $item) {
            $key = strtolower((string) ($item['input'] ?? ''));
            if ($key !== '' && ! in_array($key, $sudahAda, true)) {
                $rows[] = [ucwords(str_replace('_', ' ', $key)), $item['isian'] ?? '-'];
            }
        }

        return $rows;
    }

    private function susunAnggota(array $p): array
    {
        return array_map(
            fn (array $a) => [$a['nama'] ?? '-', ucfirst((string) ($a['peran'] ?? 'Anggota'))],
            $p['anggota'] ?? []
        );
    }

    private function susunDokumen(array $p): array
    {
        return array_map(function (array $d) {
            $link = $d['link_dokumen'] ?? '';
            if ($link === '') {
                $url = '#';
            } elseif (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
                $url = $link;
            } else {
                $url = Storage::disk('public')->url($link);
            }

            return [
                $d['nama_file'] ?? '-',
                ucfirst((string) ($d['tipe_dokumen'] ?? 'Dokumen')),
                $url,
            ];
        }, $p['dokumen'] ?? []);
    }

    private function susunRiwayat(array $p): array
    {
        $namaPengaju = $p['pengaju']['nama'] ?? 'Pengaju';

        return array_map(function (array $t) use ($namaPengaju) {
            $tgl = ! empty($t['tanggal_status'])
                ? Carbon::parse($t['tanggal_status'])->locale('id')->translatedFormat('j F Y')
                : '';
            $catatan = ! empty($t['catatan_revisi']) ? ": {$t['catatan_revisi']}" : '';

            return match ($t['status'] ?? '') {
                'diajukan' => "Diajukan oleh {$namaPengaju}, {$tgl}",
                'direvisi' => "Direvisi, {$tgl}{$catatan}",
                'ditolak' => "Ditolak, {$tgl}{$catatan}",
                'direview' => "Disetujui admin dan dikirim ke Kaprodi, {$tgl}",
                'diterima' => "Ditandatangani dan disetujui oleh Kaprodi, {$tgl}",
                'selesai' => "Selesai, {$tgl}",
                default => ucfirst((string) ($t['status'] ?? ''))." ({$tgl}){$catatan}",
            };
        }, $p['riwayat'] ?? []);
    }

    private function buatIsiSurat(?string $template, array $k, string $nomor, ?string $tanggalSurat = null): ?string
    {
        if (! $template) {
            return null;
        }

        $infoMap = collect($k['info'])->mapWithKeys(fn ($r) => [$r[0] => $r[1]]);

        $pemetaan = [
            '[NAMA_PENGAJU]' => $infoMap['Pengaju'] ?? '-',
            '[IDENTITAS]' => $infoMap['NIM/NIP'] ?? '-',
            '[NAMA_KEGIATAN]' => $k['judul'],
            '[TANGGAL]' => $infoMap['Tanggal'] ?? '-',
            '[LOKASI]' => $infoMap['Lokasi'] ?? ($infoMap['Penyelenggara'] ?? '-'),
            '[TANGGAL_SURAT]' => $tanggalSurat ?? Carbon::now()->locale('id')->translatedFormat('j F Y'),
            '[NOMOR_SURAT]' => $nomor,
        ];

        $teks = str_replace(array_keys($pemetaan), array_values($pemetaan), $template);

        return nl2br(e($teks));
    }

    private function identitasKaprodi(?array $snap, bool $sudahTtd): array
    {
        $dariSnap = [$snap['kaprodi']['nama'] ?? 'Nama Kaprodi', $snap['kaprodi']['nip'] ?? '-'];
        if ($sudahTtd) {
            return $dariSnap;
        }

        $s = session('kaprodi');
        $s = is_array($s) ? $s : [];
        $nip = $s['nip'] ?? null;

        return [
            $s['nama'] ?? $dariSnap[0],
            ($nip && $nip !== '-') ? $nip : $dariSnap[1],
        ];
    }
}
