<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Halaman detail Pengajuan Kegiatan (admin) terhubung ke Backend API.
 */
class AdminTinjauPengajuanController extends Controller
{
    private const NAMA_KAPRODI = 'Nama Kaprodi';

    private const NIP_KAPRODI = '198001012010101001';

    private const TOKEN_NOMOR = '@@NOMOR_SURAT@@';

    public function show(string $id)
    {
        $hasil = ApiClient::pengguna()->get("/admin/pengajuan/{$id}");
        $p = $hasil['data'] ?? [];

        $status = $this->labelStatus($p['status'] ?? 'diajukan');
        $nomorSurat = $p['nomor_sk'] ?? null;
        $catatan = $p['catatan_revisi'] ?? null;

        $info = $this->susunInfo($p);
        $anggota = $this->susunAnggota($p);
        $dokumen = $this->susunDokumen($p);
        $riwayat = $this->susunRiwayat($p);

        $k = [
            'judul' => $p['nama_kegiatan'] ?? '-',
            'info' => $info,
        ];

        $isiSurat = $this->buatIsiSurat($p['template_surat'] ?? null, $k, $nomorSurat);
        $tanggalSurat = ! empty($p['tanggal_sk'])
            ? Carbon::parse($p['tanggal_sk'])->locale('id')->translatedFormat('j F Y')
            : null;

        // Lampiran terpilih dari status is_lampiran di database atau old input saat validasi gagal
        $lampiranTerpilih = [];
        foreach (($p['dokumen'] ?? []) as $i => $doc) {
            $dipilih = session()->hasOldInput()
                ? in_array($i, array_map('intval', (array) old('lampiran', [])), true)
                : (bool) ($doc['is_lampiran'] ?? false);

            if ($dipilih) {
                $lampiranTerpilih[] = $i;
            }
        }

        return view('admin.tinjau-pengajuan', [
            'id' => (string) ($p['id_kegiatan'] ?? $id),
            'judul' => $p['nama_kegiatan'] ?? '-',
            'status' => $status,
            'info' => $info,
            'anggota' => $anggota,
            'dokumen' => $dokumen,
            'riwayat' => $riwayat,
            'nomorSurat' => $nomorSurat,
            'catatan' => $catatan,
            'isiSurat' => $isiSurat,
            'tanggalSurat' => $tanggalSurat,
            'adaLampiran' => (bool) ($p['lampiran_peserta'] ?? false),
            'peserta' => $anggota,
            'lampiranTerpilih' => $lampiranTerpilih,
            'namaKaprodi' => self::NAMA_KAPRODI,
            'nipKaprodi' => self::NIP_KAPRODI,
        ]);
    }

    public function keputusan(Request $request, string $id)
    {
        $data = $request->validate([
            'aksi' => ['required', 'in:setujui,revisi,tolak'],
            'catatan' => ['nullable', 'string', 'max:1000', 'required_if:aksi,revisi,tolak'],
            'nomor_surat' => ['nullable', 'string', 'max:100', 'required_if:aksi,setujui'],
            'lampiran' => ['nullable', 'array'],
            'lampiran.*' => ['integer', 'min:0'],
        ], [
            'catatan.required_if' => 'Catatan revisi wajib diisi',
            'nomor_surat.required_if' => 'Nomor surat wajib diisi saat menyetujui',
            'catatan.max' => 'Catatan maksimal 1000 karakter',
            'nomor_surat.max' => 'Nomor surat maksimal 100 karakter',
        ]);

        // Konversi indeks checkbox [0, 1, ...] dari form Blade menjadi id_dokumen asli dari API
        $lampiranIds = [];
        if ($data['aksi'] === 'setujui' && ! empty($data['lampiran'])) {
            $detail = ApiClient::pengguna()->get("/admin/pengajuan/{$id}")['data'] ?? [];
            $daftarDokumen = $detail['dokumen'] ?? [];

            foreach (array_unique(array_map('intval', $data['lampiran'])) as $idx) {
                if (isset($daftarDokumen[$idx]['id_dokumen'])) {
                    $lampiranIds[] = (int) $daftarDokumen[$idx]['id_dokumen'];
                }
            }
        }

        $payload = [
            'aksi' => $data['aksi'],
            'nomor_sk' => $data['aksi'] === 'setujui' ? trim((string) $data['nomor_surat']) : null,
            'tanggal_sk' => $data['aksi'] === 'setujui' ? now()->toDateString() : null,
            'catatan_revisi' => $data['aksi'] !== 'setujui' ? trim((string) $data['catatan']) : null,
            'lampiran' => $lampiranIds,
        ];

        $respons = ApiClient::pengguna()->post("/admin/pengajuan/{$id}/keputusan", $payload);

        return redirect()
            ->route('admin.pengajuan.show', $id)
            ->with('success', $respons['message'] ?? 'Keputusan pengajuan berhasil disimpan.');
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

        // Tambahkan isian dinamis lainnya jika ada
        $sudahAda = ['penyelenggara', 'lokasi', 'tanggal_mulai', 'tanggal_selesai'];
        foreach (($p['isian'] ?? []) as $item) {
            $key = strtolower((string) ($item['input'] ?? ''));
            if ($key !== '' && ! in_array($key, $sudahAda, true)) {
                $label = ucwords(str_replace('_', ' ', $key));
                $rows[] = [$label, $item['isian'] ?? '-'];
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
                'direvisi' => "Direvisi oleh Admin, {$tgl}{$catatan}",
                'ditolak' => "Ditolak oleh Admin, {$tgl}{$catatan}",
                'direview' => "Disetujui admin dan dikirim ke Kaprodi, {$tgl}",
                'diterima' => "Disetujui Kaprodi, {$tgl}",
                'selesai' => "Selesai, {$tgl}",
                default => ucfirst((string) ($t['status'] ?? ''))." ({$tgl}){$catatan}",
            };
        }, $p['riwayat'] ?? []);
    }

    private function labelStatus(string $status): string
    {
        return match ($status) {
            'diajukan' => 'Diajukan',
            'direvisi' => 'Direvisi',
            'ditolak' => 'Ditolak',
            'direview' => 'Menunggu Tanda Tangan',
            'diterima', 'selesai' => 'Disetujui',
            default => ucfirst($status),
        };
    }

    private function buatIsiSurat(?string $template, array $k, ?string $nomorSurat): string
    {
        if (! $template) {
            return e('Kategori ini tidak memiliki template surat balasan.');
        }

        $isi = nl2br(e($this->isiTeks($template, $k, self::TOKEN_NOMOR)));
        $nomor = trim((string) $nomorSurat);
        $tampil = $nomor !== '' ? e($nomor) : '[nomor surat belum diisi]';

        return str_replace(
            self::TOKEN_NOMOR,
            '<span class="js-nomor-surat">'.$tampil.'</span>',
            $isi
        );
    }

    private function isiTeks(string $template, array $k, string $nomor, ?string $tanggalSurat = null): string
    {
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

        return str_replace(array_keys($pemetaan), array_values($pemetaan), $template);
    }
}
