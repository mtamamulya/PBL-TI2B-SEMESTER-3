<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ProfilProdi;
use App\Support\ProfilProdiLanding;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Unggah & hapus logo Profil Prodi (admin dan kaprodi); API dipalsukan dengan Http::fake. */
class ProfilProdiLogoTest extends TestCase
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

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->post(route('admin.prodi.profil.logo.store'))->assertRedirect(route('login'));
        $this->post(route('kaprodi.prodi.profil.logo.store'))->assertRedirect(route('login'));
        $this->delete(route('admin.prodi.profil.logo.destroy', 'utama'))->assertRedirect(route('login'));
        $this->delete(route('kaprodi.prodi.profil.logo.destroy', 'footer'))->assertRedirect(route('login'));
    }

    #[DataProvider('peran')]
    public function test_unggah_logo_meneruskan_multipart_ke_api_dan_membersihkan_cache(string $prefix, int $idRole): void
    {
        Cache::put(ProfilProdiLanding::CACHE_KEY, ['lama' => true], 300);
        Cache::put(ProfilProdiLanding::CACHE_GAGAL, true, 30);

        $dataApi = array_merge(ProfilProdi::cadangan(), [
            'logo_url' => 'http://localhost:8000/storage/prodi/utama-baru.png',
            'logo_footer_url' => null,
        ]);

        Http::fake(['*/prodi/profil/logo' => Http::response([
            'success' => true,
            'message' => 'Logo berhasil diunggah.',
            'data' => $dataApi,
        ], 201)]);

        $file = UploadedFile::fake()->create('logo-trk.png', 200, 'image/png');

        $this->masuk($prefix, $idRole)
            ->post(route("{$prefix}.prodi.profil.logo.store"), [
                'jenis' => 'utama',
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.logo_url', 'http://localhost:8000/storage/prodi/utama-baru.png');

        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/prodi/profil/logo')
            && $r->hasFile('file'));

        $this->assertFalse(Cache::has(ProfilProdiLanding::CACHE_KEY));
        $this->assertFalse(Cache::has(ProfilProdiLanding::CACHE_GAGAL));
    }

    #[DataProvider('peran')]
    public function test_unggah_file_bukan_gambar_ditolak_422_tanpa_ke_api(string $prefix, int $idRole): void
    {
        Http::fake();

        $pdf = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $this->masuk($prefix, $idRole)
            ->post(route("{$prefix}.prodi.profil.logo.store"), [
                'jenis' => 'utama',
                'file' => $pdf,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        Http::assertNothingSent();
    }

    #[DataProvider('peran')]
    public function test_unggah_file_di_atas_2mb_ditolak_422_tanpa_ke_api(string $prefix, int $idRole): void
    {
        Http::fake();

        $besar = UploadedFile::fake()->create('logo-besar.png', 2049, 'image/png');

        $this->masuk($prefix, $idRole)
            ->post(route("{$prefix}.prodi.profil.logo.store"), [
                'jenis' => 'footer',
                'file' => $besar,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        Http::assertNothingSent();
    }

    #[DataProvider('peran')]
    public function test_jenis_tidak_valid_pada_unggah_422_dan_hapus_404(string $prefix, int $idRole): void
    {
        Http::fake();

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->masuk($prefix, $idRole)
            ->post(route("{$prefix}.prodi.profil.logo.store"), [
                'jenis' => 'samping',
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('jenis');

        $this->masuk($prefix, $idRole)
            ->deleteJson("/{$prefix}/prodi/profil/logo/samping")
            ->assertNotFound();

        Http::assertNothingSent();
    }

    #[DataProvider('peran')]
    public function test_hapus_logo_memanggil_api_delete_dan_membersihkan_cache(string $prefix, int $idRole): void
    {
        Cache::put(ProfilProdiLanding::CACHE_KEY, ['lama' => true], 300);
        Cache::put(ProfilProdiLanding::CACHE_GAGAL, true, 30);

        $dataApi = array_merge(ProfilProdi::cadangan(), [
            'logo_url' => 'http://localhost:8000/storage/prodi/utama.png',
            'logo_footer_url' => null,
        ]);

        Http::fake(['*/prodi/profil/logo/footer' => Http::response([
            'success' => true,
            'message' => 'Logo berhasil dihapus.',
            'data' => $dataApi,
        ])]);

        $this->masuk($prefix, $idRole)
            ->deleteJson(route("{$prefix}.prodi.profil.logo.destroy", 'footer'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.logo_footer_url', null);

        Http::assertSent(fn ($r) => $r->method() === 'DELETE'
            && str_ends_with($r->url(), '/prodi/profil/logo/footer'));

        $this->assertFalse(Cache::has(ProfilProdiLanding::CACHE_KEY));
        $this->assertFalse(Cache::has(ProfilProdiLanding::CACHE_GAGAL));
    }

    #[DataProvider('peran')]
    public function test_api_mati_menghasilkan_503(string $prefix, int $idRole): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->masuk($prefix, $idRole)
            ->post(route("{$prefix}.prodi.profil.logo.store"), [
                'jenis' => 'utama',
                'file' => $file,
            ], ['Accept' => 'application/json'])
            ->assertStatus(503);

        $this->masuk($prefix, $idRole)
            ->deleteJson(route("{$prefix}.prodi.profil.logo.destroy", 'utama'))
            ->assertStatus(503);
    }
}
