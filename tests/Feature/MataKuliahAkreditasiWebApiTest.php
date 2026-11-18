<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminAkreditasiController;
use App\Http\Controllers\AdminMataKuliahController;
use App\Http\Controllers\AkreditasiController;
use App\Http\Controllers\KaprodiMataKuliahController;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Halaman Mata Kuliah dan Akreditasi mengambil data lewat API (ApiClient), bukan langsung dari Store. */
class MataKuliahAkreditasiWebApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function masuk(int $role): void
    {
        $this->actingAs(User::query()->where('id_role', $role)->firstOrFail())
            ->withSession(['api_token' => 'token-uji']);
    }

    private function amplop(mixed $data, int $terakhir = 1): array
    {
        return ['success' => true, 'message' => 'ok', 'data' => $data, 'meta' => ['last_page' => $terakhir]];
    }

    private function mk(array $ubah = []): array
    {
        return $ubah + [
            'id_mk' => 5, 'id_kurikulum' => 7, 'semester' => 1, 'kode_mk' => 'B-1', 'nama_mk' => 'Basis Data',
            'sks_teori' => 2, 'sks_praktik' => 1, 'jam_teori' => 2, 'jam_praktek' => 2,
        ];
    }

    private function kurikulum(): array
    {
        return ['id_kurikulum' => 7, 'nama_kurikulum' => 'Kurikulum 2026', 'tahun_terbit' => 2026];
    }

    private function akreditasi(array $ubah = []): array
    {
        return $ubah + [
            'id_akreditasi' => 3, 'lembaga' => 'LAM INFOKOM', 'nomor_sk' => '123/SK', 'tahun_terbit' => 2024,
            'berlaku_sampai' => '2029-12-31', 'status' => true, 'link' => 'http://x/a.pdf',
        ];
    }

    // ---------- Mata Kuliah ----------

    public function test_daftar_mata_kuliah_admin_dan_kaprodi_dari_api(): void
    {
        Http::fake([
            '*/mata-kuliah*' => Http::response($this->amplop([
                $this->mk(['semester' => 3, 'id_mk' => 6, 'kode_mk' => 'A-1']),
                $this->mk(),
            ])),
            '*/kurikulum*' => Http::response($this->amplop([$this->kurikulum()])),
        ]);

        foreach ([[1, AdminMataKuliahController::class], [2, KaprodiMataKuliahController::class]] as [$role, $kelas]) {
            $this->masuk($role);
            $this->get(action([$kelas, 'index']))->assertOk()
                ->assertViewHas('kurikulumId', '7')
                ->assertViewHas('semester', 1)          // semester pertama yang punya data
                ->assertViewHas('jumlahSemua', 1);
        }

        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer token-uji'));
    }

    public function test_galat_validasi_api_tampil_di_kolom_form(): void
    {
        Http::fake(['*/kurikulum/7/mata-kuliah' => Http::response(
            ['success' => false, 'message' => 'tidak valid', 'errors' => ['kode_mk' => ['Kode mata kuliah sudah dipakai pada kurikulum ini.']]], 422
        )]);
        $this->masuk(1);

        $this->post(action([AdminMataKuliahController::class, 'store']), [
            'kurikulum' => '7', 'semester' => 1, 'kode' => 'B-1', 'nama' => 'Basis Data',
            'sks_teori' => 2, 'sks_praktek' => 1, 'jam_teori' => 2, 'jam_praktek' => 2,
        ])->assertSessionHasErrors('kode');
    }

    public function test_kaprodi_hapus_mata_kuliah_lewat_api(): void
    {
        Http::fake(fn (HttpRequest $r) => Http::response(
            $r->method() === 'DELETE' ? ['success' => true, 'message' => 'ok', 'data' => null] : $this->amplop($this->mk())
        ));
        $this->masuk(2);

        $this->delete(action([KaprodiMataKuliahController::class, 'destroy'], [5]))
            ->assertRedirect()->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/mata-kuliah/5'));
    }

    public function test_server_api_mati_menjawab_503_di_mata_kuliah(): void
    {
        Http::fake(fn () => throw new ConnectionException('mati'));
        $this->masuk(1);

        $this->get(action([AdminMataKuliahController::class, 'index']))->assertStatus(503);
    }

    // ---------- Akreditasi ----------

    public function test_daftar_akreditasi_dibentuk_untuk_view(): void
    {
        Http::fake(['*/akreditasi*' => Http::response($this->amplop([$this->akreditasi()]))]);
        $this->masuk(1);

        $this->get(action([AdminAkreditasiController::class, 'index']))->assertOk()->assertViewHas('akreditasi', [[
            'id' => '3', 'lembaga' => 'LAM INFOKOM', 'peringkat' => '', 'nomor_sk' => '123/SK',
            'year' => '2024', 'berlaku_sampai' => '2029-12-31', 'status' => 'aktif', 'pdf_url' => 'http://x/a.pdf',
        ]]);
    }

    public function test_aktifkan_memanggil_api_lalu_mengirim_ulang_semua_baris(): void
    {
        Http::fake(['*/akreditasi*' => Http::response($this->amplop([$this->akreditasi()]))]);
        $this->masuk(1);

        $this->patchJson(action([AdminAkreditasiController::class, 'status'], ['3']))
            ->assertOk()->assertJsonPath('data.0.status', 'aktif');

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/akreditasi/3/aktifkan'));
    }

    public function test_hapus_akreditasi_aktif_ditolak_422(): void
    {
        Http::fake(fn (HttpRequest $r) => $r->method() === 'DELETE'
            ? Http::response(['success' => false, 'message' => 'Akreditasi yang aktif tidak bisa dihapus.', 'errors' => []], 409)
            : Http::response($this->amplop([$this->akreditasi()])));
        $this->masuk(1);

        $this->deleteJson(action([AdminAkreditasiController::class, 'destroy'], ['3']))
            ->assertStatus(422)->assertJsonPath('message', 'Akreditasi yang aktif tidak bisa dihapus.');
    }

    public function test_halaman_publik_akreditasi_tanpa_aktif_tidak_error(): void
    {
        Http::fake(['*/akreditasi/aktif' => Http::response(['success' => false, 'message' => 'Belum ada', 'errors' => []], 404)]);

        $this->get(action([AkreditasiController::class, 'index']))->assertOk()->assertViewHas('pdfUrl', null);
    }

    public function test_halaman_publik_akreditasi_503_bila_api_mati(): void
    {
        Http::fake(fn () => throw new ConnectionException('mati'));

        $this->get(action([AkreditasiController::class, 'index']))->assertStatus(503);
    }
}


