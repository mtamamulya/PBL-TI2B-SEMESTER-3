<?php

namespace Tests\Feature\BackendKel3;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AksesDosenTest extends TestCase
{
    use RefreshDatabase;

    private const OPSI = '/api/v1/pengajaran/opsi';

    private function akun(string $role): User
    {
        return User::create([
            'id_role' => Role::firstOrCreate(['nama_role' => $role])->getKey(),
            'email' => $role.'@example.test',
            'password' => 'sandi-uji',
            'status' => true,
        ]);
    }

    private function login(User $user, string $device = 'browser'): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'sandi-uji',
            'device_name' => $device,
        ])->assertOk()->json('data.token');
    }

    public function test_dosen_aktif_bisa_membaca_identitas_dan_mengakses_modul(): void
    {
        $user = $this->akun('dosen');
        $this->withToken($this->login($user))->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.id_user', $user->getKey());

        $this->getJson(self::OPSI)->assertOk();
    }

    public function test_mahasiswa_bisa_login_tetapi_tidak_bisa_membuka_opsi_dosen(): void
    {
        $token = $this->login($this->akun('mahasiswa'));

        $this->withToken($token)->getJson(self::OPSI)
            ->assertForbidden()->assertJsonPath('success', false);
    }

    public function test_dosen_yang_dinonaktifkan_setelah_login_langsung_ditolak(): void
    {
        $user = $this->akun('dosen');
        $token = $this->login($user);
        $this->withToken($token)->getJson(self::OPSI)->assertOk();

        $user->update(['status' => false]);
        $this->app['auth']->forgetGuards();
        $this->getJson(self::OPSI)->assertForbidden()->assertJsonPath('success', false);
    }

    public function test_logout_mencabut_token_perangkat_yang_dipakai(): void
    {
        $user = $this->akun('dosen');
        $browser = $this->login($user);
        $ponsel = $this->login($user, 'ponsel');

        $this->withToken($browser)->postJson('/api/v1/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::OPSI)->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($ponsel)->getJson(self::OPSI)->assertOk();
    }

    public function test_login_dibatasi_setelah_lima_percobaan(): void
    {
        $input = ['email' => 'dosen@example.test', 'password' => 'salah'];
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', $input)->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', $input)->assertTooManyRequests()->assertHeader('Retry-After');
    }
}
