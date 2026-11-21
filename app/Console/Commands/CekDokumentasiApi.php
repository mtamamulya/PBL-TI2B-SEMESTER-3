<?php

namespace App\Console\Commands;

use App\Support\ApiDokumentasi;
use Illuminate\Console\Command;

class CekDokumentasiApi extends Command
{
    protected $signature = 'api:dokumentasi-cek';

    protected $description = 'Bandingkan docs/openapi.yaml dengan route API yang benar-benar ada';

    public function handle(): int
    {
        $dok = ApiDokumentasi::endpoint();
        $nyata = ApiDokumentasi::routeNyata();
        $kunciDok = [];
        $masalah = 0;

        foreach ($dok as $e) {
            $k = ApiDokumentasi::kunci($e);
            $kunciDok[$k] = true;
            $ada = isset($nyata[$k]);

            if (! $e['belum_aktif'] && ! $ada) {
                $this->error("Ada di dokumentasi tapi route TIDAK ADA: {$k}");
                $masalah++;
            } elseif ($e['belum_aktif'] && $ada) {
                $this->warn("Route sudah ada, hapus x-belum-aktif di dokumentasi: {$k}");
                $masalah++;
            }
        }

        foreach (array_keys($nyata) as $k) {
            if (! isset($kunciDok[$k]) && ! str_starts_with($k, 'head ')) {
                $this->warn("Route ada tapi BELUM didokumentasikan: {$k}");
            }
        }

        $this->info(count($dok).' endpoint dibaca dari dokumentasi.');

        return $masalah ? self::FAILURE : self::SUCCESS;
    }
}
