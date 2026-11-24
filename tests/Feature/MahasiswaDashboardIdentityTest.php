<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MahasiswaDashboardIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'student-'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_dashboard_uses_authenticated_student_identity(): void
    {
        $this->actingAs($this->student())->get(route('mahasiswa.dashboard'))
            ->assertOk()
            ->assertSee(config('mahasiswa.nama'))
            ->assertSee('NIM:')
            ->assertSee(config('mahasiswa.nim'))
            ->assertSee('Angkatan '.config('mahasiswa.angkatan'))
            ->assertDontSee('NIP:');
    }

    public function test_student_dashboard_uses_configured_semester(): void
    {
        $this->actingAs($this->student())->get(route('mahasiswa.index'))
            ->assertOk()
            ->assertViewIs('mahasiswa.mahasiswa.dashboard-mhs')
            ->assertSee(config('mahasiswa.nama'))
            ->assertSee('>'.config('mahasiswa.semester').'<', false);
    }

    public function test_profile_and_header_show_the_logout_action(): void
    {
        $this->actingAs($this->student())->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertViewIs('mahasiswa.mahasiswa.profil')
            ->assertSee(config('mahasiswa.nama'))
            ->assertSee(config('mahasiswa.nim'))
            ->assertSee('Keluar')
            ->assertSee('action="'.route('logout').'"', false);

        $this->get(route('mahasiswa.dashboard'))
            ->assertOk()
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Keluar');
    }

    public function test_profile_password_modal_uses_the_profile_otp_api(): void
    {
        $user = $this->student();
        $user->update(['email' => 'ivan.mahasiswa@example.test']);

        $this->actingAs($user)
            ->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertSee('Kode OTP akan dikirim ke i*************@example.test.')
            ->assertSee('x-text="pesanError"', false)
            ->assertSee('Ubah email')
            ->assertSee('/api/v1/profil/ganti-email/kirim-otp', false)
            ->assertSee('/api/v1/profil/ganti-email/verifikasi', false)
            ->assertSee('/api/v1/profil/ganti-sandi/kirim-otp', false)
            ->assertSee('/api/v1/profil/ganti-sandi/verifikasi', false)
            ->assertSee('/api/v1/profil/ganti-sandi/simpan', false)
            ->assertDontSee('alert(\'Kata sandi berhasil disimpan!\')', false);
    }

    public function test_profile_shows_local_otp_override_for_mahasiswa(): void
    {
        $user = $this->student();
        $user->update(['email' => 'student-original@test.local']);
        $this->app->detectEnvironment(fn () => 'local');
        config([
            'mahasiswa.otp_email' => 'ivan.43325111@mhs.polines.ac.id',
        ]);

        $maskedEmail = 'i'.str_repeat('*', strlen('ivan.43325111') - 1).'@mhs.polines.ac.id';
        $this->actingAs($user)
            ->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertSee('Kode OTP akan dikirim ke '.$maskedEmail.'.');
    }

    public function test_student_can_log_out_using_the_shared_logout_route(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_role');
        $userId = DB::table('users')->insertGetId([
            'id_role' => $roleId,
            'email' => 'mahasiswa-logout@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_user');

        $this->actingAs(User::findOrFail($userId))
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_student_can_log_in_and_is_redirected_to_the_dashboard(): void
    {
        config(['api_client.token_sesi' => true]);
        Mail::fake();
        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'id_role' => 4,
            'email' => 'mahasiswa-login@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'mahasiswa-login@test.local',
            'password' => 'password',
        ])
            ->assertRedirect(route('mahasiswa.dashboard'));

        $this->assertAuthenticated();
        $token = session('api_token');
        $this->assertNotEmpty($token);

        $this->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertSee($token, false)
            ->assertSee('headers.Authorization = `Bearer ${this.apiToken}`', false);

        $this->withToken($token)
            ->postJson('/api/v1/profil/ganti-sandi/kirim-otp')
            ->assertOk();

        Mail::assertSent(PasswordResetOtp::class, fn ($mail) => $mail->hasTo('mahasiswa-login@test.local'));
    }

    public function test_inactive_student_cannot_log_in(): void
    {
        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'id_role' => 4,
            'email' => 'mahasiswa-inactive@test.local',
            'password' => bcrypt('password'),
            'status' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'mahasiswa-inactive@test.local',
            'password' => 'password',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
