<?php

namespace Tests\Feature\BackendKel3;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrestasiDosenTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/dosen/prestasi';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_route_publik_dan_dosen_terpisah(): void
    {
        $this->getJson('/api/v1/prestasi')->assertOk();
        $this->get('/prestasi-trk')->assertOk();
        $this->getJson(self::API)->assertUnauthorized();
        $this->actingAs(User::where('email', 'mahasiswa@trk.ac.id')->firstOrFail(), 'sanctum')
            ->getJson(self::API)->assertForbidden();
    }

    public function test_opsi_hanya_menawarkan_kegiatan_dosen_yang_diterima(): void
    {
        $dosen = User::where('email', 'dosen@trk.ac.id')->firstOrFail();
        $this->actingAs($dosen, 'sanctum');
        $jenis = DB::table('jenis_kegiatan')->insertGetId(['nama_jenis_kegiatan' => 'Kegiatan Dosen'], 'id_jenis_kegiatan');
        $kategori = DB::table('kategori')->insertGetId(['id_jenis_kegiatan' => $jenis, 'nama_kategori' => 'Lomba'], 'id_kategori');
        foreach (['diterima', 'diajukan'] as $status) {
            $id = DB::table('kegiatan')->insertGetId(['id_user' => $dosen->getKey(), 'id_kategori' => $kategori, 'nama_kegiatan' => 'Lomba '.$status], 'id_kegiatan');
            DB::table('timeline')->insert(['id_kegiatan' => $id, 'status' => $status, 'tanggal_status' => now()]);
        }
        $this->getJson(self::API.'/opsi')->assertOk()->assertJsonCount(1, 'data.kegiatan')
            ->assertJsonPath('data.kegiatan.0.nama', 'Lomba diterima');
    }

    public function test_sesi_web_dosen_dapat_mengelola_prestasi_dan_bukti(): void
    {
        config(['sanctum.stateful' => ['localhost:8000']]);
        $login = $this->post('/login', ['email' => 'dosen@trk.ac.id', 'password' => 'password']);
        $login->assertRedirect();
        $cookie = config('session.cookie');
        $this->withCredentials()->withCookie($cookie, $login->getCookie($cookie)->getValue())
            ->withHeader('Origin', 'http://localhost:8000');
        $this->app['auth']->forgetGuards();
        $this->getJson(self::API.'/opsi')->assertOk()->assertJsonPath('data.pengaju.nama', 'dosen@trk.ac.id');

        $input = [
            'sumber' => 'mandiri', 'bentuk' => 'Individu', 'nama_prestasi' => 'Lomba TRK',
            'jenis' => 'Non-akademik', 'tingkat' => 'Provinsi', 'penyelenggara' => 'TRK',
            'tanggal_mulai' => '2026-11-10', 'tanggal_selesai' => '2026-11-11', 'capaian' => 'Juara 1',
            'dokumen' => [['jenis' => 'Sertifikat', 'tautan' => 'https://example.test/bukti']],
        ];
        $id = $this->postJson(self::API, $input)->assertCreated()->json('data.id_prestasi');
        $this->getJson("/api/v1/prestasi/$id")->assertOk()->assertJsonPath('data.id_prestasi', $id);
        $url = self::API.'/'.$id;
        $this->getJson(self::API)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->getJson($url)->assertOk()->assertJsonPath('data.bukti_prestasi.0.link', 'https://example.test/bukti');
        $input['nama_prestasi'] = 'Juara TRK';
        $this->putJson($url, $input)->assertOk()->assertJsonPath('data.nama_lomba', 'Juara TRK');

        $owner = User::where('email', 'dosen@trk.ac.id')->firstOrFail();
        $lain = User::create(['id_role' => $owner->id_role, 'email' => 'lain@example.test', 'status' => true, 'password' => 'sandi-uji']);
        $this->actingAs($lain, 'sanctum')->getJson($url)->assertNotFound();
        $this->putJson($url, $input)->assertNotFound();
        $this->deleteJson($url)->assertNotFound();

        $this->actingAs($owner, 'sanctum')->deleteJson($url)->assertOk();
        $this->assertDatabaseMissing('prestasi', ['id_prestasi' => $id]);
        $this->assertDatabaseMissing('bukti_prestasi', ['id_prestasi' => $id]);
    }
}
