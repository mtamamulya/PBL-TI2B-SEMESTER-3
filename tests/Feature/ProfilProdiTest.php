<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ProfilProdi;
use App\Support\ProfilProdiLanding;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Halaman Profil Prodi (web) untuk admin dan kaprodi; API dipalsukan dengan Http::fake. */
class ProfilProdiTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

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

    /** Profil dari API: semua kunci kontrak, sebagian terisi. */
    private function profil(array $timpa = []): array
    {
        return array_merge(ProfilProdi::cadangan(), [
            'visi' => 'Visi dari API',
            'misi' => ['Misi satu'],
            'email' => 'prodi@polines.ac.id',
        ], $timpa);
    }

    private function respon(array $data): PromiseInterface
    {
        return Http::response(['success' => true, 'message' => 'ok', 'data' => $data]);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('admin.prodi.profil'))->assertRedirect(route('login'));
        $this->get(route('kaprodi.prodi.profil'))->assertRedirect(route('login'));
        $this->put(route('admin.prodi.profil.update'), [])->assertRedirect(route('login'));
    }

    #[DataProvider('peran')]
    public function test_halaman_menampilkan_data_dari_api(string $prefix, int $idRole): void
    {
        Http::fake(['*/prodi/profil' => $this->respon($this->profil())]);

        $res = $this->masuk($prefix, $idRole)->get(route("{$prefix}.prodi.profil"));

        $res->assertOk()
            ->assertViewIs('admin.profil-prodi')
            ->assertSee('Visi dari API')
            ->assertDontSee('Mode dummy');
        $this->assertSame($prefix, $res->viewData('prefix'));
        $this->assertEqualsCanonicalizing(ProfilProdi::KUNCI, array_keys($res->viewData('profil')));
    }

    public function test_api_mengembalikan_sebagian_kunci_tetap_dilengkapi(): void
    {
        Http::fake(['*/prodi/profil' => $this->respon(['kode_prodi' => 'TRK'])]);

        $res = $this->masuk('admin', 1)->get(route('admin.prodi.profil'))->assertOk();

        $this->assertEqualsCanonicalizing(ProfilProdi::KUNCI, array_keys($res->viewData('profil')));
        $this->assertNull($res->viewData('profil')['email']);
        $this->assertSame([], $res->viewData('profil')['misi']);
    }

    #[DataProvider('peran')]
    public function test_update_diteruskan_ke_api_dan_cache_landing_dibersihkan(string $prefix, int $idRole): void
    {
        Cache::put(ProfilProdiLanding::CACHE_KEY, ['lama' => true], 300);
        Cache::put(ProfilProdiLanding::CACHE_GAGAL, true, 30);
        Http::fake(['*/prodi/profil' => $this->respon($this->profil(['no_wa' => '6281234567890', 'misi' => ['Satu', 'Dua']]))]);

        $this->masuk($prefix, $idRole)
            ->putJson(route("{$prefix}.prodi.profil.update"), [
                'no_wa' => '0812-3456-7890',
                'misi' => ['Satu', '  ', 'Dua'],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.no_wa', '6281234567890')
            ->assertJsonPath('data.misi', ['Satu', 'Dua']);

        Http::assertSent(fn ($r) => $r->method() === 'PUT'
            && str_ends_with($r->url(), '/prodi/profil')
            && $r['no_wa'] === '6281234567890'
            && $r['misi'] === ['Satu', 'Dua']);
        $this->assertFalse(Cache::has(ProfilProdiLanding::CACHE_KEY));
        $this->assertFalse(Cache::has(ProfilProdiLanding::CACHE_GAGAL));
    }

    public function test_validasi_lokal_menolak_sebelum_ke_api(): void
    {
        Http::fake();

        $this->masuk('admin', 1)->putJson(route('admin.prodi.profil.update'), [
            'jenjang' => 'S2', 'email' => 'bukan-email', 'no_wa' => '123', 'nama_prodi' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['jenjang', 'email', 'no_wa', 'nama_prodi']);

        Http::assertNothingSent();
    }

    public function test_api_membalas_422_diteruskan(): void
    {
        Http::fake(['*/prodi/profil' => Http::response([
            'success' => false, 'message' => 'Kode prodi sudah dipakai.',
            'errors' => ['kode_prodi' => ['Kode prodi sudah dipakai.']],
        ], 422)]);

        $this->masuk('admin', 1)->putJson(route('admin.prodi.profil.update'), ['kode_prodi' => 'TRK'])
            ->assertStatus(422);
    }

    public function test_api_mati_halaman_dan_simpan_menghasilkan_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->masuk('admin', 1)->get(route('admin.prodi.profil'))->assertStatus(503);
        $this->masuk('admin', 1)->putJson(route('admin.prodi.profil.update'), ['visi' => 'x'])->assertStatus(503);
    }

    public function test_menu_admin_memuat_dropdown_data_prodi_dan_akreditasi_tidak_berdiri_sendiri(): void
    {
        $menu = collect(config('admin.menu'));
        $prodi = $menu->firstWhere('label', 'Data Prodi');

        $this->assertNotNull($prodi);
        $this->assertSame(['Profil Prodi', 'Akreditasi'], array_column($prodi['children'], 'label'));
        $this->assertNull($menu->firstWhere('label', 'Akreditasi'));
    }

    public function test_menu_kaprodi_data_prodi_langsung_ke_profil(): void
    {
        $prodi = collect(config('kaprodi.menu'))->firstWhere('label', 'Data Prodi');

        $this->assertNotNull($prodi);
        $this->assertArrayNotHasKey('children', $prodi);
        $this->assertSame('kaprodi.prodi.profil', $prodi['route']);
    }

    public function test_kontrak_bawaan_dan_cadangan_memuat_semua_kunci(): void
    {
        $this->assertEqualsCanonicalizing(ProfilProdi::KUNCI, array_keys(ProfilProdi::bawaan()));
        $this->assertEqualsCanonicalizing(ProfilProdi::KUNCI, array_keys(ProfilProdi::cadangan()));
    }

    public function test_cadangan_tidak_memuat_data_contoh(): void
    {
        $semua = json_encode(ProfilProdi::cadangan());

        $this->assertStringNotContainsString('contoh_trk', $semua);
        $this->assertStringNotContainsString('6281234567890', $semua);
        $this->assertStringNotContainsString('trk@polines.ac.id', $semua);
    }
}
