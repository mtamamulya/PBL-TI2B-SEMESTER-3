<?php

namespace App\Http\Controllers;

use App\BackendKel1\KegiatanAdmin\IsianKegiatanAdmin;
use App\BackendKel1\KegiatanAdmin\PesertaKegiatan;
use App\Support\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Halaman "Tambah Kegiatan" Admin dan Kaprodi. Isian dan unggahan mengikuti master kategori.
 *
 * - Kategori: dari API GET /kategori-kegiatan (kategori buatan admin), form mengirim id_kategori.
 * - Pemilik kegiatan dosen/mahasiswa dipilih dari tabel dosen atau mahasiswa.
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
            'kandidat' => $this->kandidat(),
        ]);
    }

    public function kategori(int $id): JsonResponse
    {
        $kategori = ApiClient::pengguna()->get('/kategori-kegiatan/'.$id)['data'] ?? [];
        abort_unless(($kategori['kebutuhan'] ?? null) === 'kegiatan', 404);

        $isian = $kategori['isian'];
        foreach ($isian as &$field) {
            if (PesertaKegiatan::isian($kategori['jenis'] ?? '', $field)) {
                $field['jenis_input'] = PesertaKegiatan::TIPE;
            }
            $field['pilihan'] ??= IsianKegiatanAdmin::pilihanLama($field['input'], $field['jenis_input']);
        }

        return response()->json(['isian' => $isian]);
    }

    /** Simpan kegiatan baru lewat API. */
    public function store(Request $request): RedirectResponse
    {
        $dinamis = $request->boolean('isian_dinamis') || $request->has('detail');
        $data = $request->validate([
            'id_kategori' => ['required', 'integer'],
            'nama_kegiatan' => ['required', 'string', 'max:200'],
        ] + ($dinamis ? [
            'pemilik' => ['nullable', 'string', 'regex:/^(Dosen|Mahasiswa):\d+$/'],
            'anggota' => ['prohibited'],
            'dokumen' => ['prohibited'],
            'detail' => ['sometimes', 'array'],
            'detail.*' => ['array'],
        ] : [
            'anggota' => ['required', 'array', 'min:1'],
            'anggota.*' => ['required', 'regex:/^(Dosen|Mahasiswa):\d+$/'],
            'dokumen' => ['nullable', 'array'],
            'dokumen.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt', 'max:5120'],
            'dokumen.*.nama' => ['nullable', 'required_with:dokumen.*.file', 'string', 'max:150'],
            'dokumen.*.jenis' => ['nullable', 'required_with:dokumen.*.file', Rule::in(self::JENIS_DOKUMEN)],
            'dokumen.*.keterangan' => ['nullable', 'string', 'max:500'],
            'dokumen.*.tautan' => ['nullable', 'url', 'max:255'],
            'jenis_kegiatan' => ['required', Rule::in(self::JENIS_KEGIATAN)],
            'penyelenggara' => ['required', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
        ]), [
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
            'anggota.prohibited' => 'Isi bagian anggota sesuai isian kategori yang tampil.',
            'dokumen.prohibited' => 'Isi dokumen lewat isian kategori yang tampil.',
            'pemilik.regex' => 'Pemilik kegiatan tidak valid.',
            'dokumen.*.file.mimes' => 'Format file harus pdf, jpg, jpeg, png, doc, docx, xls, xlsx, atau txt.',
            'dokumen.*.file.max' => 'Ukuran file maksimal 5 MB.',
            'dokumen.*.nama.required_with' => 'Nama dokumen wajib diisi bila ada file.',
            'dokumen.*.jenis.required_with' => 'Jenis dokumen wajib dipilih bila ada file.',
            'dokumen.*.tautan.url' => 'Tautan dokumen harus berupa URL yang valid (https://...).',
        ]);

        // Multipart tidak boleh berisi larik bersarang, jadi pakai kunci berkurung (API menormalkannya sendiri).
        $kirim = [
            'id_kategori' => $data['id_kategori'],
            'nama_kegiatan' => $data['nama_kegiatan'],
        ];
        $berkas = [];
        if ($dinamis) {
            $kirim['isian_dinamis'] = 1;
            if (isset($data['pemilik'])) {
                $kirim['pemilik'] = $data['pemilik'];
            }
            foreach ($data['detail'] ?? [] as $idIsian => $jawaban) {
                foreach ($jawaban as $baris => $nilai) {
                    // Kunci numerik juga menjaga konstruksi nama field multipart tetap konsisten.
                    if (! ctype_digit((string) $idIsian) || ! ctype_digit((string) $baris)) {
                        throw ValidationException::withMessages(['detail' => 'Format jawaban kategori tidak valid.']);
                    }
                    $kunci = "detail[$idIsian][$baris]";
                    if ($nilai instanceof UploadedFile) {
                        $berkas[$kunci] = $nilai;
                    } else {
                        if ($nilai !== null && ! is_scalar($nilai)) {
                            throw ValidationException::withMessages(['detail' => 'Format jawaban kategori tidak valid.']);
                        }
                        $kirim[$kunci] = $nilai ?? '';
                    }
                }
            }
        } else {
            $kirim += array_intersect_key($data, array_flip(['jenis_kegiatan', 'penyelenggara', 'tanggal_mulai', 'tanggal_selesai']));
            // Pertahankan bentuk kiriman untuk pemanggil form lama.
            foreach (array_values($data['anggota']) as $i => $a) {
                $kirim["anggota[$i]"] = $a;
            }

            $n = 0;
            foreach ($request->input('dokumen', []) as $i => $dok) {
                $file = $request->file("dokumen.$i.file");

                if (! $file && empty($dok['tautan']) && empty($dok['nama'])) {
                    continue;
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
        }

        $hasil = ApiClient::pengguna()->post('/admin/kegiatan', $kirim, $berkas);

        $tab = $hasil['data']['jenis'] ?? self::TAB[$data['jenis_kegiatan'] ?? ''] ?? 'semua';
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
     * Kandidat pemilik, dengan key "Tipe:id" memakai ID profil.
     *
     * @return array<int, array{key:string, nama:string, tipe:string}>
     */
    private function kandidat(): array
    {
        $kandidat = [];

        foreach (DB::table('dosen as d')->join('users as u', 'u.id_user', '=', 'd.id_user')
            ->orderBy('d.nama')->get(['d.id_dosen', 'd.nama', 'd.nip as identitas', 'u.status', 'u.id_role']) as $r) {
            $kandidat[] = ['key' => 'Dosen:'.$r->id_dosen, 'nama' => $r->nama, 'tipe' => 'Dosen', 'identitas' => $r->identitas, 'aktif' => (bool) $r->status && (int) $r->id_role === 3];
        }

        $angkatan = PesertaKegiatan::mahasiswaAngkatan()->pluck('m.tahun_masuk', 'm.id_mahasiswa');
        foreach (DB::table('mahasiswa as m')->join('users as u', 'u.id_user', '=', 'm.id_user')
            ->orderBy('m.nama')->get(['m.id_mahasiswa', 'm.nama', 'm.nim as identitas', 'm.status_mahasiswa', 'u.status', 'u.id_role']) as $r) {
            $kandidat[] = [
                'key' => 'Mahasiswa:'.$r->id_mahasiswa, 'nama' => $r->nama, 'tipe' => 'Mahasiswa', 'identitas' => $r->identitas,
                'aktif' => (bool) $r->status && (int) $r->id_role === 4 && $r->status_mahasiswa === 'Aktif',
                'angkatan' => $angkatan[$r->id_mahasiswa] ?? null,
            ];
        }

        return $kandidat;
    }
}
