<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ProfilProdi;
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
        $this->put(route('admin.prodi.profil.update'), [])->assertRedirect(route('login'));
    }

    /** @dataProvider peran */
    public function test_halaman_menampilkan_data_dummy(string $prefix, int $idRole): void
    {
        $this->masuk($prefix, $idRole)->get(route("{$prefix}.prodi.profil"))
            ->assertOk()
            ->assertSee('Profil Prodi')
            ->assertSee('Teknologi Rekayasa Komputer')
            ->assertSee('Mode dummy');
    }

    /** @dataProvider peran */
    public function test_update_menyimpan_dan_dinormalkan(string $prefix, int $idRole): void
    {
        $this->masuk($prefix, $idRole)
            ->putJson(route("{$prefix}.prodi.profil.update"), [
                'visi' => '  Visi baru  ',
                'no_wa' => '0812-3456-7890',
                'misi' => ['Satu', '   ', 'Dua'],
                'profil_lulusan' => [['judul' => 'Analis', 'deskripsi' => ''], ['judul' => '', 'deskripsi' => '']],
                'youtube' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.visi', 'Visi baru')
            ->assertJsonPath('data.no_wa', '6281234567890')
            ->assertJsonPath('data.misi', ['Satu', 'Dua'])
            ->assertJsonPath('data.profil_lulusan.0.judul', 'Analis')
            ->assertJsonCount(1, 'data.profil_lulusan')
            ->assertJsonPath('data.youtube', null);

        $this->get(route("{$prefix}.prodi.profil"))->assertSee('Visi baru');
    }

    public function test_update_sebagian_tidak_menghapus_bagian_lain(): void
    {
        $this->masuk('admin', 1)->putJson(route('admin.prodi.profil.update'), ['visi' => 'Hanya visi'])->assertOk()
            ->assertJsonPath('data.nama_prodi', 'Teknologi Rekayasa Komputer');
    }

    public function test_validasi_menolak_data_tidak_valid(): void
    {
        $this->masuk('admin', 1)->putJson(route('admin.prodi.profil.update'), [
            'jenjang' => 'S2',
            'email' => 'bukan-email',
            'no_wa' => '123',
            'instagram' => 'bukan url',
            'nama_prodi' => '',
        ])->assertStatus(422)->assertJsonValidationErrors(['jenjang', 'email', 'no_wa', 'instagram', 'nama_prodi']);
    }

    public function test_kontrak_bawaan_memuat_semua_kunci(): void
    {
        $this->assertEqualsCanonicalizing(ProfilProdi::KUNCI, array_keys(ProfilProdi::bawaan()));
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
