<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * KONTRAK DATA PROFIL PRODI — acuan semua fase (halaman admin/kaprodi, landing, API).
 * Bentuk data di sini = bentuk JSON API `GET/PUT /api/v1/prodi/profil` (Fase 4).
 * Jangan ubah nama/tipe kunci tanpa kabar ke tim; lihat docs/kontrak-profil-prodi.md.
 */
class ProfilProdi
{
    /** Semua kunci yang dibaca halaman & landing. */
    public const KUNCI = [
        'kode_prodi', 'nama_prodi', 'jenjang',
        'deskripsi', 'visi', 'misi', 'tujuan', 'profil_lulusan',
        'email', 'no_wa', 'alamat',
        'instagram', 'youtube', 'tiktok', 'facebook',
        'logo_url', 'logo_footer_url',
    ];

    /** Kunci yang boleh diubah lewat PUT (logo dikelola endpoint terpisah). */
    public const DAPAT_DIUBAH = [
        'kode_prodi', 'nama_prodi', 'jenjang',
        'deskripsi', 'visi', 'misi', 'tujuan', 'profil_lulusan',
        'email', 'no_wa', 'alamat',
        'instagram', 'youtube', 'tiktok', 'facebook',
    ];

    private const DAFTAR = ['misi', 'tujuan'];

    /** Data contoh (mode dummy) sekaligus data bawaan landing bila API tidak tersedia. */
    public static function bawaan(): array
    {
        return [
            'kode_prodi' => 'TRK',
            'nama_prodi' => 'Teknologi Rekayasa Komputer',
            'jenjang' => 'D4',
            'deskripsi' => 'Program studi yang mempelajari perancangan perangkat keras dan perangkat lunak komputer secara terpadu.',
            'visi' => 'Menjadi program studi unggulan di bidang rekayasa komputer yang menghasilkan lulusan kompeten dan berdaya saing.',
            'misi' => [
                'Menyelenggarakan pendidikan vokasi berbasis kompetensi di bidang rekayasa komputer.',
                'Melaksanakan penelitian terapan yang bermanfaat bagi industri dan masyarakat.',
                'Menjalin kerja sama dengan industri dan perguruan tinggi.',
            ],
            'tujuan' => [
                'Menghasilkan lulusan yang siap kerja dan mampu berwirausaha.',
                'Menghasilkan karya terapan yang diakui di tingkat nasional.',
            ],
            'profil_lulusan' => [
                ['judul' => 'Software Engineer', 'deskripsi' => 'Merancang dan membangun perangkat lunak aplikatif.'],
                ['judul' => 'Embedded System Engineer', 'deskripsi' => 'Mengembangkan sistem tertanam dan perangkat IoT.'],
            ],
            'email' => 'trk@polines.ac.id',
            'no_wa' => '6281234567890',
            'alamat' => 'Jl. Prof. Soedarto, S.H., Tembalang, Semarang',
            'instagram' => 'https://instagram.com/contoh_trk',
            'youtube' => null,
            'tiktok' => null,
            'facebook' => null,
            'logo_url' => null,
            'logo_footer_url' => null,
        ];
    }

    /** Aturan validasi. Semua `sometimes` supaya tiap bagian form boleh disimpan sendiri-sendiri. */
    public static function aturan(): array
    {
        $url = ['sometimes', 'nullable', 'url:http,https', 'max:255'];

        return [
            'kode_prodi' => ['sometimes', 'required', 'string', 'max:20'],
            'nama_prodi' => ['sometimes', 'required', 'string', 'max:100'],
            'jenjang' => ['sometimes', 'required', Rule::in(['D3', 'D4'])],
            'deskripsi' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'visi' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'misi' => ['sometimes', 'array', 'max:20'],
            'misi.*' => ['string', 'max:500'],
            'tujuan' => ['sometimes', 'array', 'max:20'],
            'tujuan.*' => ['string', 'max:500'],
            'profil_lulusan' => ['sometimes', 'array', 'max:20'],
            'profil_lulusan.*.judul' => ['required', 'string', 'max:150'],
            'profil_lulusan.*.deskripsi' => ['nullable', 'string', 'max:1000'],
            'email' => ['sometimes', 'nullable', 'email', 'max:100'],
            'no_wa' => ['sometimes', 'nullable', 'regex:/^62[0-9]{8,13}$/'],
            'alamat' => ['sometimes', 'nullable', 'string', 'max:500'],
            'instagram' => $url,
            'youtube' => $url,
            'tiktok' => $url,
            'facebook' => $url,
        ];
    }

    public static function pesan(): array
    {
        return [
            'no_wa.regex' => 'Nomor WhatsApp tidak valid. Contoh: 081234567890.',
            'profil_lulusan.*.judul.required' => 'Judul profil lulusan wajib diisi.',
        ];
    }

    /**
     * Rapikan input sebelum validasi: hanya kunci DAPAT_DIUBAH yang ADA di input,
     * teks dipangkas, kosong -> null, daftar dibuang item kosongnya, no WA -> format 62xxxxxxxxxx.
     */
    public static function bersihkan(array $input): array
    {
        $hasil = [];

        foreach (self::DAPAT_DIUBAH as $kunci) {
            if (! array_key_exists($kunci, $input)) {
                continue;
            }
            $nilai = $input[$kunci];

            if (in_array($kunci, self::DAFTAR, true)) {
                $hasil[$kunci] = array_values(array_filter(
                    array_map(fn ($v) => is_string($v) ? trim($v) : '', (array) $nilai),
                    fn ($v) => $v !== ''
                ));
            } elseif ($kunci === 'profil_lulusan') {
                $hasil[$kunci] = collect((array) $nilai)
                    ->filter(fn ($b) => is_array($b))
                    ->map(fn ($b) => [
                        'judul' => trim((string) ($b['judul'] ?? '')),
                        'deskripsi' => ($d = trim((string) ($b['deskripsi'] ?? ''))) === '' ? null : $d,
                    ])
                    ->filter(fn ($b) => $b['judul'] !== '' || $b['deskripsi'] !== null)
                    ->values()->all();
            } elseif ($kunci === 'no_wa') {
                $hasil[$kunci] = self::normalkanWa($nilai);
            } else {
                $t = is_string($nilai) ? trim($nilai) : $nilai;
                $hasil[$kunci] = $t === '' ? null : $t;
            }
        }

        return $hasil;
    }

    /** 0812.. / +62 812.. / 812.. -> 62812.. ; kosong -> null. */
    public static function normalkanWa(mixed $nilai): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $nilai);
        if ($angka === '' || $angka === null) {
            return null;
        }
        if (str_starts_with($angka, '0')) {
            return '62'.substr($angka, 1);
        }
        if (str_starts_with($angka, '8')) {
            return '62'.$angka;
        }

        return $angka;
    }
}
