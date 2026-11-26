<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\ProfilProdi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilProdiApiTest extends TestCase
{
    use RefreshDatabase;

    private function sebagai(int $idRole): static
    {
        $nama = [1 => 'admin', 2 => 'kaprodi', 3 => 'dosen', 4 => 'mahasiswa'][$idRole];
        DB::table('roles')->insertOrIgnore(['id_role' => $idRole, 'nama_role' => $nama]);
        $user = User::firstOrCreate(
            ['email' => "{$nama}@trk.ac.id"],
            ['id_role' => $idRole, 'password' => 'password', 'status' => true]
        );

        return $this->actingAs($user, 'sanctum');
    }

    private function buatProdi(): void
    {
        DB::table('prodi')->insert([
            'kode_prodi' => 'TRK', 'nama_prodi' => 'Teknologi Rekayasa Komputer', 'jenjang' => 'D4',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_get_publik_tanpa_token_dan_bentuk_sesuai_kontrak(): void
    {
        $this->buatProdi();

        $res = $this->getJson('/api/v1/prodi/profil')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kode_prodi', 'TRK')
            ->assertJsonPath('data.misi', [])
            ->assertJsonPath('data.logo_url', null);

        $this->assertEqualsCanonicalizing(ProfilProdi::KUNCI, array_keys($res->json('data')));
    }

    public function test_get_tanpa_baris_prodi_mengembalikan_data_kosong(): void
    {
        $this->getJson('/api/v1/prodi/profil')->assertOk()
            ->assertJsonPath('data.kode_prodi', null)
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.profil_lulusan', []);
    }

    public function test_tulis_butuh_token_dan_peran_admin_atau_kaprodi(): void
    {
        $this->buatProdi();

        $this->putJson('/api/v1/prodi/profil', ['visi' => 'x'])->assertStatus(401);
        $this->sebagai(3)->putJson('/api/v1/prodi/profil', ['visi' => 'x'])->assertStatus(403);
        $this->sebagai(4)->putJson('/api/v1/prodi/profil', ['visi' => 'x'])->assertStatus(403);
        $this->sebagai(1)->putJson('/api/v1/prodi/profil', ['visi' => 'x'])->assertOk();
        $this->sebagai(2)->putJson('/api/v1/prodi/profil', ['visi' => 'y'])->assertOk();
    }

    public function test_update_sebagian_dan_daftar_tersimpan_sebagai_json(): void
    {
        $this->buatProdi();

        $this->sebagai(1)->putJson('/api/v1/prodi/profil', [
            'visi' => 'Visi baru',
            'no_wa' => '0812-3456-7890',
            'misi' => ['Satu', '  ', 'Dua'],
            'profil_lulusan' => [['judul' => 'Analis', 'deskripsi' => 'Mengolah data']],
        ])->assertOk()
            ->assertJsonPath('data.no_wa', '6281234567890')
            ->assertJsonPath('data.misi', ['Satu', 'Dua'])
            ->assertJsonPath('data.nama_prodi', 'Teknologi Rekayasa Komputer');

        $this->getJson('/api/v1/prodi/profil')
            ->assertJsonPath('data.visi', 'Visi baru')
            ->assertJsonPath('data.profil_lulusan.0.judul', 'Analis');
    }

    public function test_validasi_422(): void
    {
        $this->buatProdi();

        $this->sebagai(1)->putJson('/api/v1/prodi/profil', [
            'jenjang' => 'S2', 'email' => 'bukan-email', 'no_wa' => '123', 'instagram' => 'bukan url',
        ])->assertStatus(422)->assertJsonValidationErrors(['jenjang', 'email', 'no_wa', 'instagram']);
    }

    public function test_tanpa_baris_prodi_put_harus_menyertakan_identitas(): void
    {
        $this->sebagai(1)->putJson('/api/v1/prodi/profil', ['visi' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['kode_prodi', 'nama_prodi', 'jenjang']);

        $this->sebagai(1)->putJson('/api/v1/prodi/profil', [
            'kode_prodi' => 'TRK', 'nama_prodi' => 'Teknologi Rekayasa Komputer', 'jenjang' => 'D4',
        ])->assertOk();

        $this->assertSame(1, DB::table('prodi')->count());
    }

    public function test_unggah_ganti_dan_hapus_logo(): void
    {
        Storage::fake('public');
        $this->buatProdi();

        $res = $this->sebagai(1)->postJson('/api/v1/prodi/profil/logo', [
            'jenis' => 'utama', 'file' => UploadedFile::fake()->image('logo.png', 200, 200),
        ])->assertStatus(201);

        $path = DB::table('prodi')->value('logo');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($res->json('data.logo_url'));
        $this->assertNull($res->json('data.logo_footer_url'));

        $this->sebagai(2)->postJson('/api/v1/prodi/profil/logo', [
            'jenis' => 'utama', 'file' => UploadedFile::fake()->image('baru.jpg', 200, 200),
        ])->assertStatus(201);
        Storage::disk('public')->assertMissing($path);

        $baru = DB::table('prodi')->value('logo');
        $this->sebagai(1)->deleteJson('/api/v1/prodi/profil/logo/utama')->assertOk()
            ->assertJsonPath('data.logo_url', null);
        Storage::disk('public')->assertMissing($baru);
        $this->assertNull(DB::table('prodi')->value('logo'));
    }

    public function test_unggah_logo_ditolak_untuk_file_salah_dan_peran_salah(): void
    {
        Storage::fake('public');
        $this->buatProdi();

        $this->sebagai(3)->postJson('/api/v1/prodi/profil/logo', [
            'jenis' => 'utama', 'file' => UploadedFile::fake()->image('a.png'),
        ])->assertStatus(403);

        $this->sebagai(1)->postJson('/api/v1/prodi/profil/logo', [
            'jenis' => 'utama', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
        ])->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->sebagai(1)->postJson('/api/v1/prodi/profil/logo', [
            'jenis' => 'lain', 'file' => UploadedFile::fake()->image('a.png'),
        ])->assertStatus(422)->assertJsonValidationErrors(['jenis']);

        $this->sebagai(1)->deleteJson('/api/v1/prodi/profil/logo/lain')->assertStatus(404);
    }
}
