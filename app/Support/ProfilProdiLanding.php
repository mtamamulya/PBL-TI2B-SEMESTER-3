<?php

namespace App\Support;

use Throwable;

/**
 * Satu sumber data Profil Prodi untuk seluruh tampilan publik (navbar, footer, landing).
 *
 * FASE 3 (sekarang): membaca mode dummy dari session, dengan fallback ProfilProdi::bawaan().
 * FASE 5 (nanti): cukup ganti isi method ambil() ke ApiClient::publik() + cache + fallback bawaan.
 */
class ProfilProdiLanding
{
    private const SESSION = 'profil_prodi_dummy';

    /** @return array<string, mixed> */
    public static function ambil(): array
    {
        $bawaan = ProfilProdi::bawaan();

        try {
            $dummy = session(self::SESSION, []);
        } catch (Throwable) {
            $dummy = [];
        }

        if (! is_array($dummy) || $dummy === []) {
            return $bawaan;
        }

        return array_merge($bawaan, $dummy);
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
