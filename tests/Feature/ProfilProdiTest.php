<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfilProdiTest extends TestCase
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

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('admin.prodi.profil'))->assertRedirect(route('login'));
        $this->get(route('kaprodi.prodi.profil'))->assertRedirect(route('login'));
    }

    /** @dataProvider peran */
    public function test_halaman_profil_prodi_terbuka(string $prefix, int $idRole): void
    {
        $this->masuk($prefix, $idRole)->get(route("{$prefix}.prodi.profil"))
            ->assertOk()->assertSee('Profil Prodi');
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
}
