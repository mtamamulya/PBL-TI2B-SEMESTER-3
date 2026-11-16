<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Membaca daftar endpoint dari docs/openapi.yaml tanpa library YAML
 * (hanya memakai aturan layout yang tertulis di kepala berkas itu).
 */
class ApiDokumentasi
{
    private const METODE = ['get', 'post', 'put', 'patch', 'delete'];

    /** @return array<int, array{metode:string, path:string, belum_aktif:bool}> */
    public static function endpoint(?string $berkas = null): array
    {
        $berkas ??= config('api_docs.file');
        $baris = preg_split('/\R/', (string) file_get_contents($berkas));

        $hasil = [];
        $dalamPaths = false;
        $path = null;
        $idx = null;

        foreach ($baris as $b) {
            if (preg_match('/^paths:\s*$/', $b)) {
                $dalamPaths = true;

                continue;
            }
            if ($dalamPaths && preg_match('/^\S/', $b) && ! str_starts_with($b, '#')) {
                break; // keluar dari paths (mis. "components:")
            }
            if (! $dalamPaths) {
                continue;
            }
            if (preg_match('#^  (/\S*):\s*$#', $b, $m)) {
                $path = $m[1];
                $idx = null;

                continue;
            }
            if ($path !== null && preg_match('/^    ('.implode('|', self::METODE).'):\s*$/', $b, $m)) {
                $hasil[] = ['metode' => $m[1], 'path' => $path, 'belum_aktif' => false];
                $idx = array_key_last($hasil);

                continue;
            }
            if ($idx !== null && preg_match('/^      x-belum-aktif:\s*true\s*$/', $b)) {
                $hasil[$idx]['belum_aktif'] = true;
            }
        }

        return $hasil;
    }

    /** Daftar route API nyata: "metode path" dengan parameter diseragamkan. */
    public static function routeNyata(): array
    {
        $ada = [];
        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();
            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }
            $path = self::seragam(substr($uri, strlen('api/v1')));
            foreach ($r->methods() as $metode) {
                $ada[strtolower($metode).' '.$path] = true;
            }
        }

        return $ada;
    }

    public static function seragam(string $path): string
    {
        return preg_replace('/\{[^}]+\}/', '{}', $path);
    }

    public static function kunci(array $e): string
    {
        return $e['metode'].' '.self::seragam($e['path']);
    }
}
