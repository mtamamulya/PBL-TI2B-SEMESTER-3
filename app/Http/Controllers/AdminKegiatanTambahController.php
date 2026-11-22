<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Halaman "Tambah Kegiatan" (admin DAN kaprodi, dibedakan dari nama route): informasi kegiatan, anggota (dosen/mahasiswa), unggah dokumen.
 *
 * - Kategori: dari API GET /kategori-kegiatan (kategori buatan admin), form mengirim id_kategori.
 * - Kandidat anggota: dari tabel dosen / mahasiswa (key "Dosen:{id_dosen}" / "Mahasiswa:{id_mahasiswa}", sesuai kontrak API).
 * - Simpan: POST /admin/kegiatan lewat ApiClient. Sengaja TANPA try/catch: 422 kembali ke form (ApiException::render),
 *   401 ke login, 403/503 tampil sebagai halaman galat, jadi API yang mati tidak tersembunyi.
 */
class AdminKegiatanTambahController extends Controller
{
    private const JENIS_KEGIATAN = ['Kegiatan Dosen', 'Kegiatan Mahasiswa', 'Kegiatan Prodi'];

    /** Harus sama dengan nama di tabel jenis_dokumen (API menolak nama yang tidak dikenal dengan 422 pada dokumen.N.jenis). */
    private const JENIS_DOKUMEN = ['Surat tugas', 'Sertifikat', 'Proposal', 'Laporan', 'Lainnya'];

    private const TAB = [
        'Kegiatan Dosen' => 'dosen',
        'Kegiatan Mahasiswa' => 'mahasiswa',
        'Kegiatan Prodi' => 'prodi',
    ];

    /** Form tambah kegiatan. */
    public function create()
    {
        return view('admin.kegiatan-tambah', [
            'kategori' => $this->daftarKategori(),
            'jenisKegiatan' => self::JENIS_KEGIATAN,
            'jenisDokumen' => self::JENIS_DOKUMEN,
            'kandidat' => $this->kandidat(),
        ]);
    }

    /** Simpan kegiatan baru lewat API. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'id_kategori' => ['required', 'integer'],
            'jenis_kegiatan' => ['required', Rule::in(self::JENIS_KEGIATAN)],
            'nama_kegiatan' => ['required', 'string', 'max:200'],
            'penyelenggara' => ['required', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'anggota' => ['required', 'array', 'min:1'],
            'anggota.*' => ['required', 'regex:/^(Dosen|Mahasiswa):\d+$/'],
            'dokumen' => ['nullable', 'array'],
            'dokumen.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt', 'max:5120'],
            'dokumen.*.nama' => ['nullable', 'required_with:dokumen.*.file', 'string', 'max:150'],
            'dokumen.*.jenis' => ['nullable', 'required_with:dokumen.*.file', Rule::in(self::JENIS_DOKUMEN)],
            'dokumen.*.keterangan' => ['nullable', 'string', 'max:500'],
            'dokumen.*.tautan' => ['nullable', 'url', 'max:255'],
        ], [
            'id_kategori.required' => 'Kategori wajib dipilih.',
            'id_kategori.integer' => 'Kategori tidak valid.',
            'jenis_kegiatan.required' => 'Jenis kegiatan wajib dipilih.',
            'nama_kegiatan.required' => 'Nama kegiatan wajib diisi.',
            'penyelenggara.required' => 'Penyelenggara wajib diisi.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'anggota.required' => 'Tambahkan minimal satu anggota kegiatan.',
            'anggota.min' => 'Tambahkan minimal satu anggota kegiatan.',
            'dokumen.*.file.mimes' => 'Format file harus pdf, jpg, jpeg, png, doc, docx, xls, xlsx, atau txt.',
            'dokumen.*.file.max' => 'Ukuran file maksimal 5 MB.',
            'dokumen.*.nama.required_with' => 'Nama dokumen wajib diisi bila ada file.',
            'dokumen.*.jenis.required_with' => 'Jenis dokumen wajib dipilih bila ada file.',
            'dokumen.*.tautan.url' => 'Tautan dokumen harus berupa URL yang valid (https://...).',
        ]);

        // Multipart tidak boleh berisi larik bersarang, jadi pakai kunci berkurung (API menormalkannya sendiri).
        $kirim = [
            'jenis_kegiatan' => $data['jenis_kegiatan'],
            'id_kategori' => $data['id_kategori'],
            'nama_kegiatan' => $data['nama_kegiatan'],
            'penyelenggara' => $data['penyelenggara'],
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
        ];

        // Urutan anggota penting: dosen/mahasiswa PERTAMA menjadi pemilik (ketua) kegiatan dosen/mahasiswa.
        foreach (array_values($data['anggota']) as $i => $a) {
            $kirim["anggota[$i]"] = $a;
        }

        $berkas = [];
        $n = 0;
        foreach ($request->input('dokumen', []) as $i => $dok) {
            $file = $request->file("dokumen.$i.file");

            if (! $file && empty($dok['tautan']) && empty($dok['nama'])) {
                continue; // kartu dokumen kosong
            }

            foreach (['nama', 'jenis', 'keterangan', 'tautan'] as $f) {
                if (! empty($dok[$f])) {
                    $kirim["dokumen[$n][$f]"] = $dok[$f];
                }
            }

            if ($file) {
                $berkas["dokumen[$n][file]"] = $file;
            }

            $n++;
        }

        $hasil = ApiClient::pengguna()->post('/admin/kegiatan', $kirim, $berkas);

        $tab = $hasil['data']['jenis'] ?? self::TAB[$data['jenis_kegiatan']];
        $peringatan = array_values(array_filter((array) ($hasil['peringatan'] ?? [])));
        $prefix = $request->routeIs('kaprodi.*') ? 'kaprodi' : 'admin';

        $redirect = redirect()
            ->route($prefix.'.kegiatan.index', ['tab' => $tab])
            ->with('success', $hasil['message'] ?? 'Kegiatan baru berhasil disimpan.');

        return $peringatan ? $redirect->with('peringatan', $peringatan) : $redirect;
    }

    /**
     * Kategori untuk kegiatan (bukan untuk surat), semua halaman, dari API.
     *
     * @return array<int, array{id_kategori:int, nama_kategori:string, jenis:?string}>
     */
    private function daftarKategori(): array
    {
        $baris = [];
        $halaman = 1;

        do {
            $hasil = ApiClient::pengguna()->get('/kategori-kegiatan', ['kebutuhan' => 'kegiatan', 'per_page' => 100, 'page' => $halaman]);
            $baris = array_merge($baris, $hasil['data'] ?? []);
            $terakhir = (int) ($hasil['meta']['last_page'] ?? 1);
            $halaman++;
        } while ($halaman <= $terakhir && $halaman <= 20);

        return array_map(fn ($k) => [
            'id_kategori' => (int) $k['id_kategori'],
            'nama_kategori' => (string) $k['nama_kategori'],
            'jenis' => $k['jenis'] ?? null,
        ], $baris);
    }

    /**
     * Kandidat anggota untuk kolom cari: dosen dan mahasiswa dari database, key = "Tipe:id" memakai id tabel.
     *
     * @return array<int, array{key:string, nama:string, tipe:string}>
     */
    private function kandidat(): array
    {
        $kandidat = [];

        foreach (DB::table('dosen')->orderBy('nama')->get(['id_dosen', 'nama']) as $r) {
            $kandidat[] = ['key' => 'Dosen:'.$r->id_dosen, 'nama' => (string) $r->nama, 'tipe' => 'Dosen'];
        }

        foreach (DB::table('mahasiswa')->orderBy('nama')->get(['id_mahasiswa', 'nama']) as $r) {
            $kandidat[] = ['key' => 'Mahasiswa:'.$r->id_mahasiswa, 'nama' => (string) $r->nama, 'tipe' => 'Mahasiswa'];
        }

        return $kandidat;
    }
}
