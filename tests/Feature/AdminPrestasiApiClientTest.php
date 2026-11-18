<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminPrestasiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Menguji kontrak web -> ApiClient tanpa mengakses database nyata. */
class AdminPrestasiApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('api_client.base_url', 'http://127.0.0.1:8001/api/v1');
        session(['api_token' => 'token-pengujian']);
    }

    public function test_index_mengambil_semua_halaman_api(): void
    {
        Http::fakeSequence()
            ->push([
                'success' => true,
                'data' => [
                    ['id_prestasi' => 3, 'tampilan' => ['id' => 3, 'nama' => 'Juara Tiga']],
                    ['id_prestasi' => 2, 'tampilan' => ['id' => 2, 'nama' => 'Juara Dua']],
                ],
                'meta' => ['last_page' => 2],
            ])
            ->push([
                'success' => true,
                'data' => [
                    ['id_prestasi' => 1, 'tampilan' => ['id' => 1, 'nama' => 'Juara Satu']],
                ],
                'meta' => ['last_page' => 2],
            ]);

        $view = (new AdminPrestasiController)->index();
        $rows = $view->getData()['rows'];

        $this->assertCount(3, $rows);
        $this->assertSame([3, 2, 1], array_column($rows, 'id'));
        Http::assertSentCount(2);
    }

    public function test_detail_memakai_proyeksi_tampilan_api(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'id_prestasi' => 7,
                    'tampilan' => ['id' => 7, 'nama' => 'Juara Nasional'],
                ],
            ], 200),
        ]);

        $view = (new AdminPrestasiController)->show(7);

        $this->assertSame('Juara Nasional', $view->getData()['prestasi']['nama']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/admin/prestasi/7'));
    }

    public function test_store_mengirim_payload_melalui_http_tanpa_token_csrf(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'Prestasi berhasil ditambahkan.'], 201),
        ]);

        $request = Request::create('/admin/prestasi', 'POST', [
            '_token' => 'csrf-web',
            'sumber' => 'mandiri',
            'id_user' => 4,
            'nama_lomba' => 'Contoh Prestasi',
        ]);

        (new AdminPrestasiController)->store($request);

        Http::assertSent(fn ($sent) => $sent->method() === 'POST'
            && $sent->hasHeader('Authorization', 'Bearer token-pengujian')
            && $sent['nama_lomba'] === 'Contoh Prestasi'
            && ! isset($sent['_token']));
    }

    public function test_update_mengirim_put_tanpa_method_spoofing(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'Prestasi diperbarui'], 200),
        ]);

        $request = Request::create('/admin/prestasi/3', 'PUT', [
            '_token' => 'csrf-web',
            '_method' => 'PUT',
            'nama_lomba' => 'Nama Diperbarui',
        ]);

        (new AdminPrestasiController)->update($request, 3);

        Http::assertSent(fn ($sent) => $sent->method() === 'PUT'
            && str_contains($sent->url(), '/api/v1/admin/prestasi/3')
            && $sent['nama_lomba'] === 'Nama Diperbarui'
            && ! isset($sent['_method']));
    }

    public function test_delete_memanggil_api_bukan_model_lokal(): void
    {
        Http::fake([
            '*' => Http::response(['success' => true, 'message' => 'Prestasi dihapus'], 200),
        ]);

        (new AdminPrestasiController)->destroy(9);

        Http::assertSent(fn ($sent) => $sent->method() === 'DELETE'
            && str_contains($sent->url(), '/api/v1/admin/prestasi/9'));
    }

    public function test_create_memakai_pilihan_form_dari_api(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'data' => [
                    'kegiatanList' => [['id' => 5, 'label' => 'Kompetisi AI']],
                    'pemilikList' => [],
                    'anggotaOptions' => [],
                ],
            ], 200),
        ]);

        $view = (new AdminPrestasiController)->create();

        $this->assertSame('Kompetisi AI', $view->getData()['kegiatanList'][0]['label']);
        Http::assertSent(fn ($sent) => $sent->method() === 'GET'
            && str_contains($sent->url(), '/api/v1/admin/prestasi/opsi'));
    }

    public function test_edit_memakai_detail_dan_pilihan_dari_api(): void
    {
        Http::fakeSequence()
            ->push([
                'success' => true,
                'data' => [
                    'id_prestasi' => 6,
                    'tampilan' => ['id' => 6, 'nama_lomba' => 'Prestasi Lama'],
                ],
            ])
            ->push([
                'success' => true,
                'data' => ['kegiatanList' => [], 'pemilikList' => [], 'anggotaOptions' => []],
            ]);

        $view = (new AdminPrestasiController)->edit(6);

        $this->assertSame('Prestasi Lama', $view->getData()['prestasi']['nama_lomba']);
        $this->assertSame([], $view->getData()['kegiatanList']);
        Http::assertSentCount(2);
    }
}
