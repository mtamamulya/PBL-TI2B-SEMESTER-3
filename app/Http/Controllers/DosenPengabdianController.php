<?php

namespace App\Http\Controllers;

use App\Support\ApiException;
use App\Support\PanggilApiWeb;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DosenPengabdianController extends Controller
{
    use PanggilApiWeb;

    /** Isian yang sudah punya kolom tetap di form, jadi tidak dirender lagi sebagai isian umum. */
    private const ISIAN_TETAP = [
        'penyelenggara', 'skim', 'kelompok_bidang', 'ranah', 'sebelumnya',
        'nomor_sk', 'tanggal_sk', 'tanggal_mulai', 'tanggal_selesai', 'deskripsi',
    ];

    private const LABEL = [
        'tahun_pelaksanaan_ke' => 'Tahun pelaksanaan ke-',
        'lama_kegiatan_tahun' => 'Lama kegiatan (tahun)',
        'lama_kegiatan' => 'Lama kegiatan (tahun)',
        'dana_dikti' => 'Dana DIKTI',
        'dana_pt' => 'Dana perguruan tinggi',
        'dana_perguruan_tinggi' => 'Dana perguruan tinggi',
        'dana_institusi_lain' => 'Dana institusi lain',
        'in_kind' => 'In kind',
    ];

    /** Daftar pengabdian dosen: data diambil dari GET /api/v1/dosen/pengabdian. */
    public function index(): View
    {
        $pengabdian = [];
        $galat = null;

        try {
            $halaman = 1;

            do {
                $respons = $this->apiGet('dosen/pengabdian', ['per_page' => 100, 'page' => $halaman]);

                foreach ($respons['data'] ?? [] as $k) {
                    $pengabdian[] = [
                        'id' => (int) $k['id_kegiatan'],
                        'nomor_sk' => $k['nomor_sk'] ?? '-',
                        'judul' => $k['nama_kegiatan'],
                        'kategori' => 'Pengabdian',
                        'tahun' => $this->tahun($k['tanggal_mulai'] ?? null),
                    ];
                }

                $terakhir = (int) ($respons['meta']['last_page'] ?? 1);
                $halaman++;
            } while ($halaman <= $terakhir && $halaman <= 20);
        } catch (ApiException $e) {
            $galat = $e->getMessage();
        }

        return view('dosen.pengabdian.index', compact('pengabdian', 'galat'));
    }

    /** Detail pengabdian: GET /api/v1/dosen/pengabdian/{id}. 404 dari API menjadi halaman 404. */
    public function show(int $id): View
    {
        $respons = $this->apiGet("dosen/pengabdian/{$id}");

        return view('dosen.pengabdian.show', ['k' => $respons['data'] ?? []]);
    }

    /** Form tambah. Isian per kategori diambil dari GET /api/v1/dosen/kategori/{id}/isian. */
    public function create(): View
    {
        return view('dosen.pengabdian.create', $this->bahanForm($this->idKategori()) + [
            'k' => null,
            'nilai' => [],
            'anggotaAwal' => [],
            'aksi' => route('pengabdian.store'),
            'metode' => 'POST',
        ]);
    }

    /** POST /api/v1/dosen/pengabdian. Galat 422 kembali ke form, 401 ke halaman login (ApiException::render). */
    public function store(Request $request): RedirectResponse
    {
        $respons = $this->apiPost('dosen/pengabdian', $this->muatan($request, $this->idKategori()));
        $id = $respons['data']['id_kegiatan'] ?? null;

        return ($id ? redirect()->route('pengabdian.show', $id) : redirect()->route('pengabdian.index'))
            ->with('status', 'Pengabdian berhasil disimpan.');
    }

    /** Form ubah, terisi dari GET /api/v1/dosen/pengabdian/{id}. */
    public function edit(int $id): View
    {
        $k = $this->apiGet("dosen/pengabdian/{$id}")['data'] ?? [];
        $idKategori = (int) ($k['id_kategori'] ?? 0) ?: $this->idKategori();

        $ketua = data_get(
            collect($k['anggota'] ?? [])->first(fn ($a) => strtolower((string) ($a['peran'] ?? '')) === 'ketua'),
            'nama'
        );
        $bahan = $this->bahanForm($idKategori, $ketua);

        $anggotaAwal = collect($k['anggota'] ?? [])
            ->reject(fn ($a) => strtolower((string) ($a['peran'] ?? '')) === 'ketua')
            ->map(fn ($a) => [
                'id_user' => $a['id_user'] ?? '',
                'nama' => $a['nama'] ?? '',
                'status' => $bahan['kandidat'][$a['nama'] ?? ''] ?? 'Dosen',
            ])->values()->all();

        return view('dosen.pengabdian.edit', $bahan + [
            'k' => $k,
            'nilai' => $this->nilaiAwal($k),
            'anggotaAwal' => $anggotaAwal,
            'aksi' => route('pengabdian.update', $id),
            'metode' => 'PUT',
        ]);
    }

    /** PUT /api/v1/dosen/pengabdian/{id}. id_kategori wajib ikut, kalau tidak isian ditolak 422. */
    public function update(Request $request, int $id): RedirectResponse
    {
        $idKategori = (int) $request->input('id_kategori') ?: $this->idKategori();

        $this->apiPut("dosen/pengabdian/{$id}", $this->muatan($request, $idKategori));

        return redirect()->route('pengabdian.show', $id)
            ->with('status', 'Perubahan pengabdian berhasil disimpan.');
    }

    /* ---------- bantu ---------- */

    private function idKategori(): int
    {
        $id = DB::table('kategori')
            ->where('nama_kategori', 'like', 'Pengabdian%')
            ->orderBy('id_kategori')
            ->value('id_kategori');

        abort_unless($id, 404, 'Kategori Pengabdian belum ada di master kategori.');

        return (int) $id;
    }

    /** Bahan untuk tampilan form: definisi isian, opsi dropdown, calon anggota. */
    private function bahanForm(int $idKategori, ?string $ketua = null): array
    {
        $definisi = collect($this->apiGet("dosen/kategori/{$idKategori}/isian")['data']['isian'] ?? []);

        $isian = $definisi
            ->reject(fn ($i) => in_array($i['input'], self::ISIAN_TETAP, true) || ($i['jenis_input'] ?? '') === 'berkas')
            ->map(fn ($i) => [
                'nama' => $i['input'],
                'label' => self::LABEL[$i['input']] ?? ucfirst(str_replace('_', ' ', $i['input'])),
                'tipe' => in_array($i['jenis_input'] ?? '', ['angka', 'tanggal', 'rupiah'], true) ? $i['jenis_input'] : 'teks',
                'wajib' => (bool) ($i['wajib'] ?? false),
            ])->values()->all();

        $ketua ??= DB::table('dosen')->where('id_user', auth()->id())->value('nama');

        $kandidat = [];
        foreach (DB::table('mahasiswa')->pluck('nama') as $nama) {
            $kandidat[$nama] = 'Mahasiswa';
        }
        foreach (DB::table('dosen')->pluck('nama') as $nama) {
            $kandidat[$nama] = 'Dosen';
        }
        if ($ketua !== null) {
            unset($kandidat[$ketua]);
        }

        return [
            'idKategori' => $idKategori,
            'isian' => $isian,
            'adaAfiliasi' => $definisi->contains('input', 'penyelenggara'),
            'opsiSkim' => $this->opsiRujukan('skim', 'nama_skim', 'pengabdian'),
            'opsiBidang' => $this->opsiRujukan('kelompok_bidang', 'nama_kelompok_bidang'),
            'kandidat' => $kandidat,
            'ketua' => $ketua,
        ];
    }

    /** Daftar nama dari tabel rujukan. Kalau ada kolom ranah, pilihan disaring dulu menurut ranah. */
    private function opsiRujukan(string $tabel, string $kolom, ?string $ranah = null): array
    {
        if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, $kolom)) {
            return [];
        }

        if ($ranah !== null && Schema::hasColumn($tabel, 'ranah')) {
            $saring = DB::table($tabel)->where('ranah', $ranah)->orderBy($kolom)->pluck($kolom)->all();

            if ($saring !== []) {
                return $saring;
            }
        }

        return DB::table($tabel)->orderBy($kolom)->pluck($kolom)->all();
    }

    /** Jawaban isian yang sudah tersimpan, kunci = nama isian. Nominal diberi titik ribuan. */
    private function nilaiAwal(array $k): array
    {
        $nilai = [];

        foreach ($k['detail'] ?? [] as $d) {
            $isi = $d['isian'] ?? null;
            $nilai[$d['input']] = ($d['jenis_input'] ?? '') === 'rupiah' && is_numeric($isi)
                ? number_format((int) $isi, 0, ',', '.')
                : $isi;
        }

        return $nilai;
    }

    /** Payload ke API. Kolom kosong dibuang, anggota memakai id_user bila ada (tidak dicari lewat nama). */
    private function muatan(Request $request, int $idKategori): array
    {
        $anggota = collect($request->input('anggota', []))
            ->filter(fn ($a) => is_array($a) && (filled($a['id_user'] ?? null) || filled($a['nama'] ?? null)))
            ->map(fn ($a) => array_filter([
                'id_user' => $a['id_user'] ?? null,
                'nama' => $a['nama'] ?? null,
                'status' => $a['status'] ?? null,
                'peran' => 'anggota',
            ], fn ($v) => $v !== null && $v !== ''))
            ->values()->all();

        $detail = array_filter((array) $request->input('detail', []), fn ($v) => $v !== null && $v !== '');

        return array_filter([
            'id_kategori' => $idKategori,
            'nama_kegiatan' => $request->input('nama_kegiatan'),
            'penyelenggara' => $request->input('penyelenggara'),
            'tanggal_mulai' => $request->input('tanggal_mulai'),
            'tanggal_selesai' => $request->input('tanggal_selesai'),
            'detail' => $detail,
            'anggota' => $anggota,
        ], fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    private function tahun(?string $tanggal): ?int
    {
        return $tanggal ? (int) substr($tanggal, 0, 4) : null;
    }
}
