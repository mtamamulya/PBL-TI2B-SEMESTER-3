<?php

namespace Tests\Feature;

use App\Support\ApiDokumentasi;
use Tests\TestCase;

class ApiDokumentasiTest extends TestCase
{
    public function test_berkas_kontrak_terbaca_dan_punya_endpoint(): void
    {
        $this->assertGreaterThan(10, count(ApiDokumentasi::endpoint()));
    }

    public function test_endpoint_yang_dianggap_aktif_punya_route_nyata(): void
    {
        $nyata = ApiDokumentasi::routeNyata();

        foreach (ApiDokumentasi::endpoint() as $e) {
            if ($e['belum_aktif']) {
                continue;
            }
            $this->assertArrayHasKey(ApiDokumentasi::kunci($e), $nyata, 'Route tidak ada: '.$e['metode'].' '.$e['path']);
        }
    }

    public function test_halaman_docs_terbuka_bila_publik(): void
    {
        config(['api_docs.publik' => true]);

        $this->get('/api/docs')->assertOk()->assertSee('swagger-ui');
        $this->get('/api/docs/openapi.yaml')->assertOk()->assertSee('openapi: 3.0.3');
    }

    public function test_halaman_docs_tertutup_di_luar_local(): void
    {
        config(['api_docs.publik' => false]);

        $this->get('/api/docs')->assertNotFound();
    }
}
