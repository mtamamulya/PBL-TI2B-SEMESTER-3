<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Halaman Skim (web) untuk admin dan kaprodi; API dipalsukan dengan Http::fake. */
class SkimWebTest extends TestCase
{
    use DatabaseMigrations;

    /** @return array<string, array{0:string,1:int}> */
    public static function peran(): array
    {
        return ['admin' => ['admin', 1], 'kaprodi' => ['kaprodi', 2]];
    }

    private function masuk(string $prefix, int $idRole): static
    {
        DB::table('roles')->insertOrIgnore(['id_role' => $idRole, 'nama_role' => $prefix]);
        $user = User::firstOrCreate(
            ['email' => "{$prefix}@trk.ac.id"],
            ['id_role' => $idRole, 'password' => 'password', 'status' => true]
        );

        return $this->actingAs($user)->withSession([$prefix => ['nama' => ucfirst($prefix)]]);
    }

    private function item(int $id, string $nama, int $total = 0): array
    {
        return ['id_skim' => $id, 'nama_skim' => $nama, 'total_kegiatan' => $total];
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('admin.kegiatan.skim'))->assertRedirect(route('login'));
        $this->get(route('kaprodi.kegiatan.skim'))->assertRedirect(route('login'));
    }

    #[DataProvider('peran')]
    public function test_daftar_skim_tampil_dari_api(string $prefix, int $idRole): void
    {
        Http::fake(['*/skim*' => Http::response([
            'success' => true, 'message' => 'ok',
            'data' => [$this->item(1, 'Penelitian Dasar', 0), $this->item(2, 'Penelitian Terapan', 3)],
        ])]);

        $res = $this->masuk($prefix, $idRole)->get(route("{$prefix}.kegiatan.skim"));

        $res->assertOk()->assertViewIs('admin.skim');
        $rows = $res->viewData('rows');
        $this->assertCount(2, $rows);
        $this->assertSame(['id' => 2, 'name' => 'Penelitian Terapan', 'total' => 3], $rows[1]);
        $this->assertSame($prefix, $res->viewData('prefix'));
    }

    #[DataProvider('peran')]
    public function test_tambah_skim_mengirim_nama_skim_ke_api(string $prefix, int $idRole): void
    {
        Http::fake(['*/skim' => Http::response(['success' => true, 'message' => 'ok', 'data' => $this->item(7, 'Hibah Internal')], 201)]);

        $res = $this->masuk($prefix, $idRole)->postJson(route("{$prefix}.kegiatan.skim.store"), ['name' => 'Hibah Internal']);

        $res->assertOk()->assertJson(['ok' => true, 'row' => ['id' => 7, 'name' => 'Hibah Internal', 'total' => 0]]);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['nama_skim'] === 'Hibah Internal');
    }

    public function test_nama_kosong_ditolak_sebelum_ke_api(): void
    {
        Http::fake();

        $this->masuk('admin', 1)->postJson(route('admin.kegiatan.skim.store'), ['name' => ''])
            ->assertStatus(422)->assertJsonValidationErrors('name');

        Http::assertNothingSent();
    }

    public function test_nama_lebih_dari_100_karakter_ditolak(): void
    {
        Http::fake();

        $this->masuk('kaprodi', 2)->postJson(route('kaprodi.kegiatan.skim.store'), ['name' => str_repeat('a', 101)])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_ubah_skim_memakai_put(): void
    {
        Http::fake(['*/skim/5' => Http::response(['success' => true, 'message' => 'ok', 'data' => $this->item(5, 'Baru', 1)])]);

        $this->masuk('admin', 1)->putJson(route('admin.kegiatan.skim.update', 5), ['name' => 'Baru'])
            ->assertOk()->assertJson(['row' => ['id' => 5, 'name' => 'Baru', 'total' => 1]]);

        Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['nama_skim'] === 'Baru');
    }

    public function test_nama_kembar_dari_api_diteruskan_sebagai_pesan(): void
    {
        Http::fake(['*/skim' => Http::response([
            'success' => false, 'message' => 'Data tidak valid.',
            'errors' => ['nama_skim' => ['Nama skim sudah digunakan.']],
        ], 422)]);

        $this->masuk('admin', 1)->postJson(route('admin.kegiatan.skim.store'), ['name' => 'Kembar'])
            ->assertStatus(422);
    }

    #[DataProvider('peran')]
    public function test_hapus_skim_berhasil(string $prefix, int $idRole): void
    {
        Http::fake(['*/skim/3' => Http::response(['success' => true, 'message' => 'ok', 'data' => []])]);

        $this->masuk($prefix, $idRole)->deleteJson(route("{$prefix}.kegiatan.skim.destroy", 3))
            ->assertOk()->assertJson(['ok' => true]);
    }

    public function test_hapus_skim_yang_dipakai_kegiatan_mengembalikan_409_dengan_pesan(): void
    {
        Http::fake(['*/skim/4' => Http::response([
            'success' => false, 'message' => 'Skim masih dipakai kegiatan dan tidak dapat dihapus.',
        ], 409)]);

        $this->masuk('admin', 1)->deleteJson(route('admin.kegiatan.skim.destroy', 4))
            ->assertStatus(409)
            ->assertJson(['message' => 'Skim masih dipakai kegiatan dan tidak dapat dihapus.']);
    }

    public function test_api_mati_menghasilkan_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->masuk('admin', 1)->get(route('admin.kegiatan.skim'))->assertStatus(503);
    }
}
