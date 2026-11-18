<?php

namespace App\BackendKel3\Pengabdian;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKegiatan;
use App\Models\DetailKegiatan;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PengabdianController extends Controller
{
    /** Kategori pengabdian dikenali dari namanya (awalan "Pengabdian"). */
    private const KATEGORI_LIKE = 'Pengabdian%';

    /** Status timeline yang mengunci perubahan. Konfirmasi dengan Revo. */
    private const STATUS_TERKUNCI = ['diterima', 'selesai'];

    /** Isian yang juga disalin ke kolom umum kegiatan (hanya bila kolomnya ada). */
    private const KOLOM_UMUM = [
        'lokasi' => 'lokasi',
        'tanggal mulai' => 'tanggal_mulai',
        'tanggal selesai' => 'tanggal_selesai',
    ];

    /** GET /dosen/pengabdian: milik atau diikuti dosen yang login. */
    public function index(Request $request): JsonResponse
    {
        $uid = $request->user()->getKey();

        $p = $this->dapatDiakses($uid)
            ->when($request->filled('q'), fn ($q) => $q->where('nama_kegiatan', 'like', '%'.$request->input('q').'%'))
            ->orderByDesc('id_kegiatan')
            ->paginate(min((int) $request->input('per_page', 15), 100));

        $status = $this->statusTerkini($p->getCollection()->pluck('id_kegiatan')->all());

        $items = $p->getCollection()->map(fn ($k) => [
            'id_kegiatan' => $k->id_kegiatan,
            'nama_kegiatan' => $k->nama_kegiatan,
            'tanggal_mulai' => $this->tgl($this->attr($k, 'tanggal_mulai')),
            'tanggal_selesai' => $this->tgl($this->attr($k, 'tanggal_selesai')),
            'peran' => (int) $k->id_user === (int) $uid ? 'ketua' : 'anggota',
            'status' => $status[$k->id_kegiatan] ?? null,
        ])->values()->all();

        return ApiResponse::paginated($p, $items);
    }

    /** GET /dosen/pengabdian/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $k = $this->dapatDiakses($request->user()->getKey())->where('id_kegiatan', $id)->first();

        if (! $k) {
            return $this->gagal('Data tidak ditemukan', 404);
        }

        return ApiResponse::ok($this->bentuk($k));
    }

    /** POST /dosen/pengabdian */
    public function store(Request $request): JsonResponse
    {
        $uid = $request->user()->getKey();

        $v = Validator::make(
            $this->masukan($request),
            $this->aturan() + ['id_kategori' => ['required', 'integer', Rule::in($this->kategoriIds())]],
            ['id_kategori.in' => 'Kategori ini bukan pengabdian.']
        );
        if ($v->fails()) {
            return $this->gagal('Validasi gagal', 422, $v->errors()->toArray());
        }
        $data = $v->validated();

        [$bersih, $salah] = $this->siapkanDetail((int) $data['id_kategori'], $data['detail'] ?? []);
        if ($salah) {
            return $this->gagal('Validasi gagal', 422, $salah);
        }

        $id = DB::transaction(function () use ($data, $bersih, $uid) {
            $k = new Kegiatan;
            $k->forceFill($this->kolomAda([
                'id_kategori' => $data['id_kategori'],
                'id_user' => $uid,
                'id_kegiatan_sebelumnya' => $data['id_kegiatan_sebelumnya'] ?? null,
                'nama_kegiatan' => $data['nama_kegiatan'],
                'lokasi' => $data['lokasi'] ?? null,
                'deskripsi' => $data['deskripsi'] ?? null,
                'nomor_sk' => $data['nomor_sk'] ?? null,
                'tanggal_sk' => $data['tanggal_sk'] ?? null,
                'tanggal_mulai' => $data['tanggal_mulai'] ?? null,
                'tanggal_selesai' => $data['tanggal_selesai'] ?? null,
            ]))->save();

            $this->tulisDetail($k, $bersih);
            $this->sinkronKolomUmum($k, $bersih, $data);
            $this->tulisAnggota($k, $data['anggota'] ?? [], true);

            return $k->getKey();
        });

        return ApiResponse::ok([
            'id_kegiatan' => $id,
            'belum_lengkap' => $this->belumLengkap((int) $data['id_kategori'], $id),
        ], 'Pengabdian berhasil disimpan', 201);
    }

    /** PATCH /dosen/pengabdian/{id}: hanya pemilik. */
    public function update(Request $request, int $id): JsonResponse
    {
        $uid = $request->user()->getKey();

        $k = $this->dapatDiakses($uid)->where('id_kegiatan', $id)->first();
        if (! $k) {
            return $this->gagal('Data tidak ditemukan', 404);
        }
        if ((int) $k->id_user !== (int) $uid) {
            return $this->gagal('Hanya pemilik kegiatan yang dapat mengubah data ini', 403);
        }

        $status = $this->statusTerkini([$id])[$id] ?? null;
        if (in_array($status, self::STATUS_TERKUNCI, true)) {
            return $this->gagal("Kegiatan berstatus {$status} dan tidak dapat diubah", 403);
        }

        $aturan = $this->aturan();
        $aturan['nama_kegiatan'] = ['sometimes', 'required', 'string', 'max:255'];

        $v = Validator::make($this->masukan($request), $aturan);
        if ($v->fails()) {
            return $this->gagal('Validasi gagal', 422, $v->errors()->toArray());
        }
        $data = $v->validated();

        [$bersih, $salah] = $this->siapkanDetail((int) $k->id_kategori, $data['detail'] ?? []);
        if ($salah) {
            return $this->gagal('Validasi gagal', 422, $salah);
        }

        DB::transaction(function () use ($k, $data, $bersih) {
            $umum = ['nama_kegiatan', 'lokasi', 'deskripsi', 'nomor_sk', 'tanggal_sk',
                'tanggal_mulai', 'tanggal_selesai', 'id_kegiatan_sebelumnya'];
            $k->forceFill($this->kolomAda(array_intersect_key($data, array_flip($umum))))->save();

            if (array_key_exists('detail', $data)) {
                // jawaban untuk isian yang disebut di payload diganti seluruhnya
                $ids = array_values(array_unique(array_column($data['detail'] ?? [], 'id_isian_kategori')));
                DetailKegiatan::query()
                    ->where('id_kegiatan', $k->getKey())
                    ->whereIn('id_isian_kategori', $ids)
                    ->get()->each->delete();

                $this->tulisDetail($k, $bersih);
                $this->sinkronKolomUmum($k, $bersih, $data);
            }

            if (array_key_exists('anggota', $data)) {
                AnggotaKegiatan::query()
                    ->where('id_kegiatan', $k->getKey())
                    ->where('id_user', '!=', $k->id_user)
                    ->get()->each->delete();

                $this->tulisAnggota($k, $data['anggota'] ?? [], false);
            }
        });

        return ApiResponse::ok($this->bentuk($k->refresh()), 'Pengabdian berhasil diperbarui');
    }

    /* ---------- validasi ---------- */

    private function aturan(): array
    {
        return [
            'nama_kegiatan' => ['required', 'string', 'max:255'],
            'lokasi' => ['nullable', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'tanggal_sk' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'id_kegiatan_sebelumnya' => ['nullable', 'integer', 'exists:kegiatan,id_kegiatan'],
            'detail' => ['nullable', 'array'],
            'detail.*.id_isian_kategori' => ['required', 'integer'],
            'detail.*.isian' => ['nullable'],
            'anggota' => ['nullable', 'array'],
            'anggota.*.id_user' => ['required', 'integer', 'exists:users,id_user'],
            'anggota.*.peran' => ['nullable', Rule::in(['ketua', 'anggota'])],
        ];
    }

    /**
     * Cocokkan jawaban dengan definisi isian kategori (jenis, batas jumlah).
     * Jawaban kosong tidak disimpan. Aturan wajib TIDAK memblokir (lihat belumLengkap).
     *
     * @return array{0: array, 1: array} [jawaban bersih, error]
     */
    private function siapkanDetail(int $idKategori, array $detail): array
    {
        $isian = DB::table('isian_kategori')->where('id_kategori', $idKategori)->get()->keyBy('id_isian_kategori');
        $bersih = [];
        $errors = [];
        $hitung = [];

        foreach ($detail as $i => $d) {
            $row = $isian->get($d['id_isian_kategori']);
            if (! $row) {
                $errors["detail.$i.id_isian_kategori"][] = 'Isian bukan milik kategori ini.';

                continue;
            }

            $nilai = $d['isian'] ?? null;
            if ($nilai === null || ! is_scalar($nilai)) {
                if ($nilai !== null) {
                    $errors["detail.$i.isian"][] = 'Harus berupa nilai tunggal.';
                }

                continue;
            }
            $nilai = trim((string) $nilai);
            if ($nilai === '' || $row->jenis_input === 'berkas') {
                continue; // kosong tidak disimpan; berkas ditangani modul Dokumen
            }

            if ($row->jenis_input === 'rupiah') {
                $nilai = preg_replace('/\D/', '', $nilai); // titik ribuan dibuang
            }

            $salah = match ($row->jenis_input) {
                'angka' => is_numeric($nilai) ? null : 'Harus berupa angka.',
                'rupiah' => $nilai !== '' ? null : 'Harus berupa nominal rupiah.',
                'tanggal' => $this->tanggalValid($nilai) ? null : 'Format tanggal harus YYYY-MM-DD.',
                default => null,
            };
            if ($salah) {
                $errors["detail.$i.isian"][] = $salah;

                continue;
            }

            $hitung[$row->id_isian_kategori] = ($hitung[$row->id_isian_kategori] ?? 0) + 1;
            if ($row->jumlah_isian !== null && $hitung[$row->id_isian_kategori] > (int) $row->jumlah_isian) {
                $errors["detail.$i.id_isian_kategori"][] = "Jawaban '{$row->input}' maksimal {$row->jumlah_isian}.";

                continue;
            }

            $bersih[] = [
                'id_isian_kategori' => (int) $row->id_isian_kategori,
                'input' => $row->input,
                'isian' => $nilai,
            ];
        }

        return [$bersih, $errors];
    }

    private function tanggalValid(string $v): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $v);

        return $d && $d->format('Y-m-d') === $v;
    }

    /* ---------- tulis ---------- */

    /** Lewat model supaya validasi DetailKegiatan (event model) ikut jalan. */
    private function tulisDetail(Kegiatan $k, array $bersih): void
    {
        foreach ($bersih as $b) {
            (new DetailKegiatan)->forceFill([
                'id_kegiatan' => $k->getKey(),
                'id_isian_kategori' => $b['id_isian_kategori'],
                'isian' => $b['isian'],
            ])->save();
        }
    }

    /** Samakan kolom umum kegiatan dengan jawaban dinamis, kecuali sudah dikirim langsung. */
    private function sinkronKolomUmum(Kegiatan $k, array $bersih, array $data): void
    {
        foreach ($bersih as $b) {
            $kolom = self::KOLOM_UMUM[mb_strtolower($b['input'])] ?? null;
            if ($kolom && empty($data[$kolom]) && $this->kolomAda([$kolom => 1]) !== []) {
                $k->{$kolom} = $b['isian'];
            }
        }
        if ($k->isDirty()) {
            $k->save();
        }
    }

    private function tulisAnggota(Kegiatan $k, array $anggota, bool $ketuaBaru): void
    {
        if ($ketuaBaru) {
            (new AnggotaKegiatan)->forceFill([
                'id_kegiatan' => $k->getKey(),
                'id_user' => $k->id_user,
                'peran' => 'ketua',
            ])->save();
        }

        $sudah = [(int) $k->id_user];
        foreach ($anggota as $a) {
            $u = (int) $a['id_user'];
            if (in_array($u, $sudah, true)) {
                continue;
            }
            $sudah[] = $u;

            (new AnggotaKegiatan)->forceFill([
                'id_kegiatan' => $k->getKey(),
                'id_user' => $u,
                'peran' => $a['peran'] ?? 'anggota',
            ])->save();
        }
    }

    /* ---------- baca ---------- */

    private function kategoriIds(): array
    {
        return DB::table('kategori')
            ->where('nama_kategori', 'like', self::KATEGORI_LIKE)
            ->pluck('id_kategori')->all();
    }

    /** Pengabdian milik dosen ini atau yang dia ikuti. Penelitian dan kategori lain otomatis tersaring. */
    private function dapatDiakses($uid)
    {
        return Kegiatan::query()
            ->whereIn('id_kategori', $this->kategoriIds())
            ->where(function ($q) use ($uid) {
                $q->where('id_user', $uid)
                    ->orWhereIn('id_kegiatan', DB::table('anggota_kegiatan')->select('id_kegiatan')->where('id_user', $uid));
            });
    }

    /** Status terkini = baris timeline terakhir. */
    private function statusTerkini(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('timeline')
            ->whereIn('id_kegiatan', $ids)
            ->orderBy('tanggal_status')->orderBy('id_timeline')
            ->get(['id_kegiatan', 'status'])
            ->groupBy('id_kegiatan')
            ->map(fn ($g) => $g->last()->status)
            ->all();
    }

    /** Isian wajib (aturan tidak NULL) yang belum terjawab. Dipakai saat pengiriman. */
    private function belumLengkap(int $idKategori, int $idKegiatan): array
    {
        $terjawab = DB::table('detail_kegiatan')
            ->where('id_kegiatan', $idKegiatan)
            ->whereNotNull('isian')->where('isian', '!=', '')
            ->pluck('id_isian_kategori')->all();

        return DB::table('isian_kategori')
            ->where('id_kategori', $idKategori)
            ->whereNotNull('aturan')
            ->where('jenis_input', '!=', 'berkas')
            ->whereNotIn('id_isian_kategori', $terjawab)
            ->orderBy('id_isian_kategori')
            ->get(['id_isian_kategori', 'input'])
            ->all();
    }

    private function bentuk(Kegiatan $k): array
    {
        $id = $k->getKey();

        return [
            'id_kegiatan' => $id,
            'id_kategori' => (int) $k->id_kategori,
            'kategori' => DB::table('kategori')->where('id_kategori', $k->id_kategori)->value('nama_kategori'),
            'nama_kegiatan' => $k->nama_kegiatan,
            'lokasi' => $this->attr($k, 'lokasi'),
            'deskripsi' => $this->attr($k, 'deskripsi'),
            'nomor_sk' => $this->attr($k, 'nomor_sk'),
            'tanggal_sk' => $this->tgl($this->attr($k, 'tanggal_sk')),
            'tanggal_mulai' => $this->tgl($this->attr($k, 'tanggal_mulai')),
            'tanggal_selesai' => $this->tgl($this->attr($k, 'tanggal_selesai')),
            'id_kegiatan_sebelumnya' => $this->attr($k, 'id_kegiatan_sebelumnya'),
            'status' => $this->statusTerkini([$id])[$id] ?? null,
            // jawaban dinamis, termasuk mitra bila ada. Isian yang boleh berulang muncul sebagai beberapa baris.
            'detail' => DB::table('detail_kegiatan as d')
                ->join('isian_kategori as i', 'i.id_isian_kategori', '=', 'd.id_isian_kategori')
                ->where('d.id_kegiatan', $id)
                ->orderBy('i.id_isian_kategori')->orderBy('d.id_detail_kegiatan')
                ->get(['d.id_detail_kegiatan', 'd.id_isian_kategori', 'i.input', 'i.jenis_input', 'd.isian']),
            'belum_lengkap' => $this->belumLengkap((int) $k->id_kategori, $id),
            'anggota' => DB::table('anggota_kegiatan as a')
                ->leftJoin('dosen as d', 'd.id_user', '=', 'a.id_user')
                ->leftJoin('mahasiswa as m', 'm.id_user', '=', 'a.id_user')
                ->where('a.id_kegiatan', $id)
                ->get(['a.id_user', 'a.peran', DB::raw('COALESCE(d.nama, m.nama) as nama')]),
            'dokumen' => DB::table('dokumen')
                ->where('id_kegiatan', $id)
                ->get(['id_dokumen', 'id_detail_kegiatan', 'nama_file', 'tipe_dokumen', 'link_dokumen']),
            'timeline' => DB::table('timeline')
                ->where('id_kegiatan', $id)
                ->orderBy('tanggal_status')->orderBy('id_timeline')
                ->get(['id_timeline', 'status', 'tanggal_status', 'catatan_revisi']),
        ];
    }

    /** Peran anggota disamakan ke huruf kecil (model hanya menerima "ketua" dan "anggota"). */
    private function masukan(Request $request): array
    {
        $in = $request->all();

        if (isset($in['anggota']) && is_array($in['anggota'])) {
            foreach ($in['anggota'] as $i => $a) {
                if (is_array($a) && isset($a['peran']) && is_string($a['peran'])) {
                    $in['anggota'][$i]['peran'] = mb_strtolower(trim($a['peran']));
                }
            }
        }

        return $in;
    }

    /** Hanya kolom yang ada di tabel kegiatan (skema bisa berbeda antar versi migration). */
    private function kolomAda(array $nilai): array
    {
        return array_intersect_key($nilai, array_flip(Schema::getColumnListing('kegiatan')));
    }

    /** Baca atribut model tanpa error bila kolomnya tidak ada. */
    private function attr(Kegiatan $k, string $kolom): mixed
    {
        return $k->getAttributes()[$kolom] ?? null;
    }

    private function tgl($nilai): ?string
    {
        return $nilai === null ? null : substr((string) $nilai, 0, 10);
    }

    private function gagal(string $pesan, int $status, array $errors = []): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $pesan, 'errors' => (object) $errors], $status);
    }
}
