<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

/**
 * Satu sumber data Profil Prodi untuk seluruh tampilan publik (navbar, footer, landing).
 *
 * Alur ambil():
 *  1. Mode dummy (PROFIL_DUMMY=true): session dummy + data bawaan (hanya pengembangan).
 *  2. Normal: API publik GET /prodi/profil, di-cache 5 menit.
 *  3. API gagal: pakai data "terakhir baik"; kalau belum pernah ada, ProfilProdi::cadangan().
 *     Kegagalan ditandai 30 detik supaya landing tidak menunggu API mati di setiap kunjungan.
 * Landing TIDAK PERNAH melempar error karena data ini.
 */
class ProfilProdiLanding
{
    public const CACHE_KEY = 'profil_prodi:publik';

    public const CACHE_TERAKHIR_BAIK = 'profil_prodi:terakhir_baik';

    public const CACHE_GAGAL = 'profil_prodi:gagal';

    private const SESSION = 'profil_prodi_dummy';

    private const TTL_DETIK = 300;

    private const TANDA_GAGAL_DETIK = 30;

    /** @return array<string, mixed> */
    public static function ambil(): array
    {
        $cadangan = ProfilProdi::cadangan();

        if (ProfilProdi::modeDummy()) {
            return array_merge(ProfilProdi::bawaan(), self::dummySession());
        }

        $data = self::dariApi() ?? self::terakhirBaik();

        // Belum ada data sama sekali (API gagal & belum pernah sukses, atau baris prodi belum dibuat).
        if (! is_array($data) || blank($data['kode_prodi'] ?? null)) {
            return $cadangan;
        }

        // Kolom yang dikosongkan admin tetap kosong (null menimpa cadangan), kunci selalu lengkap.
        return array_merge($cadangan, $data);
    }

    /** @return array<string, mixed>|null null bila API tidak bisa dipakai */
    private static function dariApi(): ?array
    {
        try {
            if (Cache::has(self::CACHE_GAGAL)) {
                return null;
            }

            return Cache::remember(self::CACHE_KEY, self::TTL_DETIK, function () {
                $data = ApiClient::publik()->get('/prodi/profil')['data'] ?? null;

                if (! is_array($data)) {
                    throw new RuntimeException('Respon profil prodi tidak valid.');
                }

                Cache::forever(self::CACHE_TERAKHIR_BAIK, $data);

                return $data;
            });
        } catch (Throwable) {
            try {
                Cache::put(self::CACHE_GAGAL, true, self::TANDA_GAGAL_DETIK);
            } catch (Throwable) {
                // cache tidak tersedia: abaikan, tetap jatuh ke cadangan.
            }

            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private static function terakhirBaik(): ?array
    {
        try {
            $data = Cache::get(self::CACHE_TERAKHIR_BAIK);

            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    private static function dummySession(): array
    {
        try {
            $dummy = session(self::SESSION, []);
        } catch (Throwable) {
            return [];
        }

        return is_array($dummy) ? $dummy : [];
    }

    /** URL logo untuk navbar publik (logo_url -> fallback images/logo_trk.jpeg). */
    public static function logoNavbar(?array $profil = null): string
    {
        $profil ??= self::ambil();

        return self::selesaikanUrlLogo($profil['logo_url'] ?? null, 'images/logo_trk.jpeg');
    }

    /** URL logo untuk footer publik (logo_footer_url -> logo_url -> fallback images/logo_trk remove.png). */
    public static function logoFooter(?array $profil = null): string
    {
        $profil ??= self::ambil();

        $pilihan = filled($profil['logo_footer_url'] ?? null)
            ? $profil['logo_footer_url']
            : ($profil['logo_url'] ?? null);

        return self::selesaikanUrlLogo($pilihan, 'images/logo_trk remove.png');
    }

    private static function selesaikanUrlLogo(mixed $nilai, string $assetBawaan): string
    {
        if (! is_string($nilai) || trim($nilai) === '') {
            return asset($assetBawaan);
        }

        $url = trim($nilai);

        if (preg_match('#^(https?://|/|data:image/)#i', $url) === 1) {
            return $url;
        }

        return asset($url);
    }
}
