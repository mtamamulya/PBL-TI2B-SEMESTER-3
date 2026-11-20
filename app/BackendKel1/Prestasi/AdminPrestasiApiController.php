<?php

namespace App\BackendKel1\Prestasi;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKegiatan;
use App\Models\JenisDokumen;
use App\Models\Kegiatan;
use App\Models\Prestasi;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminPrestasiApiController extends Controller
{
    private const RELASI = [
        'user.dosen',
        'user.mahasiswa',
        'creator.dosen',
        'creator.mahasiswa',
        'kegiatan.detail.isianKategori',
        'kegiatan.kategori',
        'kegiatan.user.dosen',
        'kegiatan.user.mahasiswa',
        'kegiatan.anggota.user.dosen',
        'kegiatan.anggota.user.mahasiswa',
        'buktiPrestasi.jenisDokumen',
        'anggota.user.dosen',
        'anggota.user.mahasiswa',
    ];

    private function bukanAdmin(Request $request): bool
    {
        return $request->user()?->loadMissing('role')->role?->nama_role !== 'admin';
    }

    public function index(Request $request): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return ApiResponse::gagal('Hanya admin yang dapat mengakses data prestasi.', 403);
        }

        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $prestasi = Prestasi::with(self::RELASI)
            ->orderByDesc('id_prestasi')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Daftar prestasi berhasil diambil',
            'data' => $prestasi->getCollection()
                ->map(fn (Prestasi $item) => $item->toArray() + ['tampilan' => $this->formatRow($item)])
                ->all(),
            'meta' => [
                'current_page' => $prestasi->currentPage(),
                'per_page' => $prestasi->perPage(),
                'total' => $prestasi->total(),
                'last_page' => $prestasi->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return ApiResponse::gagal('Hanya admin yang dapat mengakses data prestasi.', 403);
        }

        $prestasi = Prestasi::with(self::RELASI)->find($id);

        if (! $prestasi) {
            return ApiResponse::gagal('Prestasi tidak ditemukan.', 404);
        }

        return ApiResponse::ok($prestasi->toArray() + ['tampilan' => $this->formatRow($prestasi)]);
    }

    public function opsi(Request $request): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return ApiResponse::gagal(
                'Hanya admin yang dapat mengakses pilihan prestasi.',
                403
            );
        }

        $namaUser = static fn (?User $user): ?string => $user?->dosen?->nama
            ?? $user?->mahasiswa?->nama
            ?? $user?->email;

        $kegiatan = Kegiatan::with([
            'user.dosen',
            'user.mahasiswa',
            'anggota.user.dosen',
            'anggota.user.mahasiswa',
            'detail.isianKategori',
        ])
            ->whereHas('statusTerkini', fn ($status) => $status->where('status', 'diterima')
            )
            ->orderBy('nama_kegiatan')
            ->get();

        $pengguna = User::with(['dosen', 'mahasiswa'])
            ->whereIn('id_role', [3, 4])
            ->orderBy('email')
            ->get();

        return ApiResponse::ok([
            'kegiatanOptions' => $kegiatan,

            'kegiatanList' => $kegiatan->map(fn (Kegiatan $k) => [
                'id' => $k->id_kegiatan,
                'label' => $k->nama_kegiatan,
                'nama' => $k->nama_kegiatan,
                'penyelenggara' => $k->nilaiIsian('penyelenggara') ?? '',
                'lokasi' => $k->nilaiIsian('lokasi') ?? '',
                'dosenPembimbing' => $k->nilaiIsian('dosen_pembimbing') ?? '',
                'tingkat' => $k->nilaiIsian('tingkat_kejuaraan')
                    ?? $k->nilaiIsian('tingkat')
                    ?? '',
                'tanggalMulai' => $k->tanggal_mulai?->toDateString() ?? '',
                'tanggalSelesai' => $k->tanggal_selesai?->toDateString() ?? '',
                'jenisPrestasi' => $k->nilaiIsian('jenis_prestasi') ?? '',
                'bidangPrestasi' => $k->nilaiIsian('bidang_lomba') ?? '',
                'idPemilik' => $k->id_user,
                'namaPemilik' => $namaUser($k->user) ?? '',
                'anggota' => $k->anggota->map(fn ($a) => [
                    'id_user' => $a->id_user,
                    'nama' => $namaUser($a->user) ?? '',
                    'peran' => ucfirst($a->peran),
                ])->values()->all(),
            ])->all(),

            'pemilikList' => $pengguna->map(fn (User $user) => [
                'id' => $user->getKey(),
                'nama' => $namaUser($user),
                'email' => $user->email,
            ])->all(),

            'tingkatList' => [
                'Kabupaten/Kota', 'Provinsi',
                'Nasional', 'Internasional',
            ],

            'capaianList' => [
                'Juara 1', 'Juara 2', 'Juara 3',
                'Harapan 1', 'Harapan 2', 'Harapan 3',
                'Finalis', 'Peserta',
            ],

            'jenisList' => ['Akademik', 'Nonakademik'],

            'jenisDokList' => [
                'Sertifikat', 'Dokumentasi kegiatan',
            ],

            'daftarMahasiswa' => DB::table('mahasiswa')
                ->orderBy('nama')
                ->pluck('nama')
                ->all(),

            'anggotaOptions' => $pengguna->map(fn (User $user) => [
                'id_user' => $user->getKey(),
                'nama' => $namaUser($user),
                'email' => $user->email,
            ])->values()->all(),

            'peranList' => ['Ketua', 'Anggota'],

            'jenisDokumenOptions' => JenisDokumen::orderBy(
                'nama_jenis_dokumen'
            )->get([
                'id_jenis_dokumen',
                'nama_jenis_dokumen',
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return ApiResponse::gagal(
                'Hanya admin yang dapat menambahkan prestasi.',
                403
            );
        }

        $data = $this->validasi($request);
        $this->simpan($data);

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil ditambahkan.',
        ], 201);
    }

    private function terapkanDataKegiatan(Request $request): void
    {
        if ($request->input('sumber') !== 'sebelumnya') {
            return;
        }

        $id = $request->input('id_kegiatan');

        if (! is_scalar($id) ||
            filter_var($id, FILTER_VALIDATE_INT) === false) {
            return;
        }

        $k = Kegiatan::with('detail.isianKategori')->find((int) $id);

        if (! $k) {
            return;
        }

        $nilai = [
            'nama_lomba' => $k->nama_kegiatan,
            'penyelenggara' => $k->nilaiIsian('penyelenggara'),
            'lokasi' => $k->nilaiIsian('lokasi'),
            'kategori' => $k->nilaiIsian('bidang_lomba'),
            'tanggal_mulai' => $k->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $k->tanggal_selesai?->toDateString(),
        ];

        $tingkat = $k->nilaiIsian('tingkat_kejuaraan')
            ?? $k->nilaiIsian('tingkat');

        if (in_array($tingkat, [
            'Kabupaten/Kota', 'Provinsi',
            'Nasional', 'Internasional',
        ], true)) {
            $nilai['tingkat'] = $tingkat;
        }

        $jenis = strtolower(trim(
            (string) $k->nilaiIsian('jenis_prestasi')
        ));

        if ($jenis === 'akademik') {
            $nilai['jenis'] = 'Akademik';
        } elseif (in_array($jenis, [
            'nonakademik', 'non-akademik', 'non akademik',
        ], true)) {
            $nilai['jenis'] = 'Nonakademik';
        }

        $request->merge(array_filter(
            $nilai,
            fn ($v) => $v !== null && trim((string) $v) !== ''
        ));
    }

    private function validasi(Request $request, ?Prestasi $item = null): array
    {
        $this->terapkanDataKegiatan($request);

        $rules = [
            'sumber' => ['required', Rule::in(['sebelumnya', 'mandiri'])],
            'id_user' => [
                'required',
                'integer',
                Rule::exists('users', 'id_user')
                    ->where(fn ($query) => $query->whereIn('id_role', [3, 4])),
            ],
            'kegiatan' => ['required_if:sumber,sebelumnya', 'nullable', 'string', 'max:150'],
            'id_kegiatan' => [
                'required_if:sumber,sebelumnya', 'nullable', 'integer',
                Rule::exists('kegiatan', 'id_kegiatan'),
            ],
            'nama_lomba' => ['required', 'string', 'max:150'],
            'penyelenggara' => ['required', 'string', 'max:150'],
            'lokasi' => ['required', 'string', 'max:150'],
            'dosen' => ['nullable', 'string', 'max:100'],
            'tingkat' => ['required', Rule::in(['Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'])],
            'capaian' => ['required', Rule::in(['Juara 1', 'Juara 2', 'Juara 3', 'Harapan 1', 'Harapan 2', 'Harapan 3', 'Finalis', 'Peserta'])],
            'jenis' => ['required', Rule::in(['Akademik', 'Nonakademik'])],
            'kategori' => ['nullable', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'bentuk' => ['required', Rule::in(['Individu', 'Tim'])],
            'anggota' => [
                Rule::excludeIf(fn () => $request->input('sumber') !== 'mandiri'
                    || $request->input('bentuk') !== 'Tim'
                ),
                'required',
                'array',
                'min:2',
            ],
            'anggota.*.id_user' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id_user')
                    ->where(fn ($query) => $query->whereIn('id_role', [3, 4])),
            ],
            'anggota.*.nama' => ['required', 'string', 'max:150'],
            'anggota.*.peran' => ['required', Rule::in(['Ketua', 'Anggota'])],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'dokumen' => ['nullable', 'array', 'max:5'],
            'dokumen.*' => ['array:url,jenis'],
            'dokumen.*.url' => [
                'nullable', 'url', 'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                    $domainDiizinkan = in_array($host, [
                        'drive.google.com',
                        'docs.google.com',
                        '1drv.ms',
                        'onedrive.live.com',
                    ], true) || str_ends_with($host, '.sharepoint.com');

                    if ($scheme !== 'https' || ! $domainDiizinkan) {
                        $fail('Tautan harus berupa tautan berbagi Google Drive atau OneDrive yang aman (https).');
                    }
                },
            ],
            'dokumen.*.jenis' => ['nullable', Rule::in(['Sertifikat', 'Dokumentasi kegiatan'])],
            'dokumen_pertahankan' => ['nullable', 'array', 'max:5'],
            'dokumen_pertahankan.*' => ['integer'],
            'dokumen_hapus' => ['nullable', 'array'],
            'dokumen_hapus.*' => ['integer'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'max' => ':attribute terlalu panjang atau terlalu banyak.',
            'after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'anggota.required' => 'Tambahkan minimal dua anggota tim.',
            'anggota.min' => 'Tim minimal terdiri dari dua orang.',
        ]);

        $validator->after(function ($validator) use ($request, $item) {
            if (
                $request->input('sumber') === 'mandiri'
                && $request->input('bentuk') === 'Tim'
            ) {
                $anggota = $request->input('anggota', []);

                if (is_array($anggota)) {
                    $idAnggota = collect($anggota)
                        ->pluck('id_user')
                        ->map(fn ($id) => (string) $id)
                        ->all();

                    if (! in_array((string) $request->input('id_user'), $idAnggota, true)) {
                        $validator->errors()->add(
                            'anggota',
                            'Pemilik prestasi wajib menjadi anggota tim.'
                        );
                    }

                    $jumlahKetua = collect($anggota)
                        ->where('peran', 'Ketua')
                        ->count();

                    if ($jumlahKetua !== 1) {
                        $validator->errors()->add(
                            'anggota',
                            'Tim harus memiliki tepat satu Ketua.'
                        );
                    }
                }
            }

            $sumber = $request->input('sumber');
            $kegiatanId = $request->input('id_kegiatan');
            if ($sumber === 'sebelumnya' && $kegiatanId) {
                $namaKegiatan = Kegiatan::whereKey($kegiatanId)->value('nama_kegiatan');

                if ($namaKegiatan !== $request->input('kegiatan')) {
                    $validator->errors()->add('kegiatan', 'Pilih kegiatan dari daftar yang tersedia.');
                }

                // Pastikan pemilik prestasi terlibat dalam kegiatan tersebut
                $pemilik = User::find($request->input('id_user'));

                if ($pemilik && ! Kegiatan::whereKey($kegiatanId)
                    ->untukPrestasi($pemilik)
                    ->exists()) {
                    $validator->errors()->add(
                        'kegiatan',
                        'Kegiatan harus sudah diterima dan melibatkan pemilik prestasi.'
                    );
                }
            }

            $dokumenList = $request->input('dokumen', []);
            $jumlahDokumenBaru = 0;
            $tautan = [];
            if (is_array($dokumenList)) {
                foreach ($dokumenList as $index => $dokumen) {
                    if (! is_array($dokumen)) {
                        continue;
                    }

                    $punyaTautan = filled($dokumen['url'] ?? null);
                    $punyaJenis = filled($dokumen['jenis'] ?? null);
                    if ($punyaTautan && $punyaJenis) {
                        $jumlahDokumenBaru++;
                        $tautan[] = strtolower($dokumen['url']);
                    }
                    if ($punyaTautan !== $punyaJenis) {
                        $field = $punyaTautan ? "dokumen.$index.jenis" : "dokumen.$index.url";
                        $validator->errors()->add($field, $punyaTautan
                            ? 'Pilih jenis dokumen untuk tautan ini.'
                            : 'Masukkan tautan untuk jenis dokumen ini.');
                    }
                }
            }

            $dokumenDipertahankan = $request->input('dokumen_pertahankan', []);
            $dokumenDipertahankan = is_array($dokumenDipertahankan) ? $dokumenDipertahankan : [];
            if ($item && $dokumenDipertahankan !== []) {
                $buktiDipertahankan = $item->buktiPrestasi()
                    ->whereIn('id_bukti_prestasi', $dokumenDipertahankan)
                    ->get(['id_bukti_prestasi', 'link']);
                if ($buktiDipertahankan->count() !== count(array_unique($dokumenDipertahankan))) {
                    $validator->errors()->add('dokumen_pertahankan', 'Dokumen yang dipertahankan tidak valid.');
                }
                foreach ($buktiDipertahankan as $bukti) {
                    $tautan[] = strtolower($bukti->link);
                }
            } elseif (! $item && $dokumenDipertahankan !== []) {
                $validator->errors()->add('dokumen_pertahankan', 'Dokumen lama tidak dapat digunakan untuk prestasi baru.');
            }

            if (count($tautan) !== count(array_unique($tautan))) {
                $validator->errors()->add('dokumen', 'Tautan dokumen tidak boleh duplikat.');
            }

            $jumlahDipertahankan = count($dokumenDipertahankan);
            if ($jumlahDokumenBaru + $jumlahDipertahankan === 0) {
                $validator->errors()->add('dokumen', 'Tambahkan minimal satu tautan bukti prestasi.');
            } elseif ($jumlahDokumenBaru + $jumlahDipertahankan > 5) {
                $validator->errors()->add('dokumen', 'Maksimal 5 dokumen.');
            }
        });

        return $validator->validate();
    }

    /** Simpan data formulir dan tautan bukti prestasi. */
    private function simpan(array $data, ?Prestasi $item = null): void
    {
        $formData = Arr::only($data, [
            'sumber', 'kegiatan', 'nama_lomba', 'penyelenggara', 'lokasi',
            'dosen', 'tingkat', 'capaian', 'jenis', 'kategori',
            'tanggal_mulai', 'tanggal_selesai', 'bentuk', 'anggota', 'deskripsi',
        ]);
        if ($data['sumber'] !== 'mandiri' || $data['bentuk'] !== 'Tim') {
            unset($formData['anggota']);
        }

        $attributes = [
            'id_kegiatan' => $data['sumber'] === 'sebelumnya' ? $data['id_kegiatan'] : null,
            'jenis_prestasi' => $data['jenis'] === 'Akademik' ? 'akademik' : 'non-akademik',
            'bidang_lomba' => $data['kategori'] ?? null,
            'id_user' => $data['id_user'],
            'created_by' => $item?->created_by ?? auth('sanctum')->id(),
            'nama_lomba' => $data['nama_lomba'],
            'penyelenggara' => $data['penyelenggara'],
            'lokasi' => $data['lokasi'],
            'dosen_pembimbing' => $data['dosen'] ?? null,
            'tingkat_kejuaraan' => strtolower($data['tingkat']),
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'bentuk_partisipasi' => strtolower($data['bentuk']),
            'deskripsi' => $data['deskripsi'] ?? null,
            'capaian' => $data['capaian'],
            'form_data' => $formData,
        ];
        $dokumenDipertahankan = $data['dokumen_pertahankan'] ?? [];

        DB::transaction(function () use ($data, $attributes, $item, $dokumenDipertahankan) {

            if ($item) {
                $item->update($attributes);

                $item->buktiPrestasi()
                    ->whereNotIn('id_bukti_prestasi', $dokumenDipertahankan)
                    ->delete();
            } else {
                $item = Prestasi::create($attributes);
            }

            if ($data['sumber'] === 'mandiri' && $data['bentuk'] === 'Tim') {
                $daftarAnggota = collect($data['anggota'])
                    ->map(fn ($anggota) => [
                        'id_user' => (int) $anggota['id_user'],
                        'peran' => strtolower($anggota['peran']),
                    ])
                    ->all();

                AnggotaKegiatan::simpanDaftar($item, $daftarAnggota);
            } else {
                $item->anggota()->delete();
            }

            foreach ($data['dokumen'] ?? [] as $dokumen) {
                if (blank($dokumen['url'] ?? null) || blank($dokumen['jenis'] ?? null)) {
                    continue;
                }

                $jenis = JenisDokumen::firstOrCreate([
                    'nama_jenis_dokumen' => $dokumen['jenis'],
                ]);
                $item->buktiPrestasi()->create([
                    'id_jenis_dokumen' => $jenis->id_jenis_dokumen,
                    'link' => $dokumen['url'],
                    'nama_file' => mb_substr(basename(parse_url($dokumen['url'], PHP_URL_PATH) ?? '') ?: $dokumen['jenis'], 0, 255),
                ]);
            }
        });
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return ApiResponse::gagal(
                'Hanya admin yang dapat memperbarui prestasi.', 403
            );
        }

        $item = Prestasi::findOrFail($id);

        $data = $this->validasi($request, $item);
        $this->simpan($data, $item);

        return ApiResponse::ok(
            ['id_prestasi' => $item->id_prestasi],
            'Prestasi berhasil diperbarui.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return ApiResponse::gagal(
                'Hanya admin yang dapat menghapus prestasi.', 403
            );
        }

        $item = Prestasi::with('buktiPrestasi')->findOrFail($id);

        $berkasLokal = $item->buktiPrestasi
            ->pluck('link')
            ->filter(fn ($path) => is_string($path)
                && str_starts_with($path, 'bukti-prestasi/'))
            ->values()
            ->all();

        DB::transaction(function () use ($item) {
            $item->buktiPrestasi()->delete();
            $item->delete();
        });

        if ($berkasLokal !== []) {
            Storage::disk('public')->delete($berkasLokal);
        }

        return ApiResponse::ok(
            null,
            'Prestasi berhasil dihapus.'
        );
    }

    /**
     * Ubah data Eloquent menjadi bentuk yang diharapkan DataTables dan
     * Blade detail/edit saat ini. Tidak mengarang data yang tidak ada di ERD.
     */
    private function formatRow(Prestasi $item): array
    {
        $kegiatan = $item->kegiatan;
        $pemilik = $item->user;
        $tanggal = $item->tanggal_selesai ?? $kegiatan?->tanggal_selesai;
        $formData = $item->form_data ?? [];

        // Daftar peserta untuk tampilan daftar/detail. Pada sumber "sebelumnya"
        // data ini milik kegiatan asal, BUKAN input anggota Prestasi Mandiri.
        $relasiAnggota = $kegiatan ? $kegiatan->anggota : $item->anggota;
        $anggota = $relasiAnggota
            ->map(fn ($a) => $this->namaUser($a->user))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $bentuk = $formData['bentuk']
            ?? ucfirst((string) ($item->bentuk_partisipasi ?? ''));
        if (! in_array($bentuk, ['Individu', 'Tim'], true)) {
            $bentuk = $item->anggota->isNotEmpty() ? 'Tim' : 'Individu';
        }

        // Field anggota yang bisa diedit hanya bersumber dari relasi Prestasi
        // mandiri. Jangan menyalin anggota kegiatan asal saat beralih sumber.
        $anggotaForm = $kegiatan ? [] : $item->anggota
            ->map(fn ($a) => [
                'id_user' => $a->id_user,
                'nama' => $this->namaUser($a->user) ?? '',
                'peran' => ucfirst($a->peran),
            ])
            ->values()
            ->all();

        // Kompatibilitas untuk catatan lama yang hanya menyimpan anggota dalam JSON.
        if (! $kegiatan && $bentuk === 'Tim' && $anggotaForm === []
            && is_array($formData['anggota'] ?? null)) {
            $anggotaForm = $formData['anggota'];
        }

        $tingkat = match (mb_strtolower((string) ($item->tingkat_kejuaraan ?? ''))) {
            'kabupaten/kota' => 'Kabupaten/Kota',
            'provinsi' => 'Provinsi',
            'nasional' => 'Nasional',
            'internasional' => 'Internasional',
            default => '',
        };

        $dokumen = $item->buktiPrestasi->map(fn ($b) => [
            'id' => $b->id_bukti_prestasi,
            'jenis' => $b->jenisDokumen?->nama_jenis_dokumen === 'Sertifikat'
                ? 'Sertifikat'
                : 'Dokumentasi kegiatan',
            'nama' => $b->nama_file ?? basename($b->link),
            'ukuran' => '',
            'url' => $b->link,
        ])->all();

        return [
            'id' => $item->id_prestasi,
            'id_kegiatan' => $item->id_kegiatan,
            'id_user' => $item->id_user,
            // Relasi database lebih tepercaya daripada form_data lama.
            'sumber' => $kegiatan ? 'sebelumnya' : 'mandiri',
            'kegiatan' => $kegiatan?->nama_kegiatan ?? '',
            'nama_lomba' => $formData['nama_lomba'] ?? $item->nama_lomba ?? $kegiatan?->nama_kegiatan ?? '',
            'penyelenggara' => $formData['penyelenggara'] ?? $item->penyelenggara ?? '',
            'lokasi' => $formData['lokasi'] ?? $item->lokasi ?? $kegiatan?->lokasi ?? '',
            'dosen' => $formData['dosen'] ?? $item->dosen_pembimbing ?? '',
            'tingkat' => $formData['tingkat'] ?? $tingkat,
            'capaian' => $formData['capaian'] ?? $item->getAttribute('capaian') ?? '',
            'jenis' => $formData['jenis'] ?? ucfirst(str_replace('-', '', (string) $item->jenis_prestasi)),
            'kategori_form' => $formData['kategori'] ?? $item->bidang_lomba ?? '',
            'tanggal_mulai' => $formData['tanggal_mulai'] ?? $item->tanggal_mulai?->toDateString() ?? $kegiatan?->tanggal_mulai?->toDateString() ?? '',
            'tanggal_selesai' => $formData['tanggal_selesai'] ?? $item->tanggal_selesai?->toDateString() ?? $kegiatan?->tanggal_selesai?->toDateString() ?? '',
            'bentuk' => $bentuk,
            'anggota_form' => $anggotaForm,
            'deskripsi' => $formData['deskripsi'] ?? $item->deskripsi ?? $item->catatan ?? '',
            'dokumen' => $dokumen,
            'nama' => ($formData['nama_lomba'] ?? null) ?: ($item->nama_lomba ?: ($kegiatan?->nama_kegiatan ?? ($item->getAttribute('capaian') ?: '-'))),
            'jenis_prestasi' => $item->jenis_prestasi,
            'bidang_lomba' => $item->bidang_lomba,
            'catatan' => $item->deskripsi ?? $item->catatan ?? '',
            // Kategori kegiatan berbeda dari bidang prestasi.
            'kategori' => $kegiatan?->kategori?->nama_kategori ?? '-',
            // Pengunggah adalah pembuat catatan, bukan otomatis pemilik prestasi.
            'pemilik_prestasi' => $this->namaUser($pemilik) ?? '-',
            'tipe' => match ((int) ($pemilik?->id_role ?? 0)) {
                3 => 'Dosen',
                4 => 'Mahasiswa',
                default => '-',
            },
            'anggota' => $anggota,
            'tanggal' => $tanggal?->locale('id')->translatedFormat('d F Y') ?? '-',
            'raw_date' => $tanggal?->toDateString(),
            'semester' => '-',
            'bukti' => $item->buktiPrestasi->map(fn ($b) => [
                'id' => $b->id_bukti_prestasi,
                'link' => $b->link,
            ])->all(),
            'gambar' => null,
        ];
    }

    private function namaUser(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return $user->dosen?->nama
            ?? $user->mahasiswa?->nama
            ?? $user->email;
    }
}
