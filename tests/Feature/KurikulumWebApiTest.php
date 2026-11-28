<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Halaman Kurikulum admin & kaprodi mengambil data lewat API (ApiClient), bukan langsung dari Store. */
class KurikulumWebApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/admin/kurikulum/trk';

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

    private function item(array $ubah = []): array
    {
        return $ubah + [
            'id_kurikulum' => 7, 'id_prodi' => 1, 'nama_kurikulum' => 'Kurikulum 2026',
            'tahun_terbit' => 2026, 'deskripsi' => 'Revisi', 'status' => true, 'link' => 'http://x/k.pdf',
        ];
    }

    private function amplop(mixed $data, array $meta = []): array
    {
        return ['success' => true, 'message' => 'ok', 'data' => $data] + ($meta ? ['meta' => $meta] : []);
    }

    public function test_daftar_diambil_dari_api_dan_dibentuk_untuk_view(): void
    {
        Http::fake(['*kurikulum*' => Http::response($this->amplop([$this->item()], ['last_page' => 1]))]);
        $this->masuk(1);

        $this->get(self::URL)->assertOk()->assertViewHas('kurikulum', [[
            'id' => '7', 'name' => 'Kurikulum 2026', 'description' => 'Revisi',
            'year' => '2026', 'status' => 'aktif', 'pdf_url' => 'http://x/k.pdf',
        ]]);

        Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer token-uji'));
    }

    public function test_semua_halaman_diambil(): void
    {
        Http::fake([
            '*page=1*' => Http::response($this->amplop([$this->item()], ['last_page' => 2])),
            '*page=2*' => Http::response($this->amplop([$this->item(['id_kurikulum' => 8])], ['last_page' => 2])),
        ]);
        $this->masuk(2);

        $this->get('/kaprodi/kurikulum/trk')->assertOk();
        Http::assertSentCount(2);
    }

    public function test_tambah_mengirim_nama_field_api(): void
    {
        Http::fake(['*kurikulum*' => Http::response($this->amplop($this->item(), []), 201)]);
        $this->masuk(1);

        $this->postJson(self::URL, ['name' => 'Kurikulum 2026', 'description' => 'Revisi', 'year' => '2026'])
            ->assertCreated()->assertJsonPath('data.name', 'Kurikulum 2026')->assertJsonPath('data.id', '7');

        Http::assertSent(fn (HttpRequest $r) => $r->method() === 'POST'
            && str_contains($r->body(), 'nama_kurikulum') && str_contains($r->body(), 'tahun_terbit'));
    }

    public function test_galat_validasi_api_diteruskan_sebagai_422(): void
    {
        Http::fake(['*kurikulum*' => Http::response(
            ['success' => false, 'message' => 'Data tidak valid', 'errors' => ['tahun_terbit' => ['Tahun terbit wajib diisi.']]], 422
        )]);
        $this->masuk(1);

        $this->postJson(self::URL, ['name' => 'X'])->assertStatus(422)
            ->assertJsonPath('errors.tahun_terbit.0', 'Tahun terbit wajib diisi.');
    }

    public function test_server_api_mati_menjawab_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('mati'));
        $this->masuk(1);

        $this->get(self::URL)->assertStatus(503);
    }
}
