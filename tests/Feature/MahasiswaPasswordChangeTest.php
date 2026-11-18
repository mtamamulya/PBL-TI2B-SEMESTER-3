<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MahasiswaPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $roleId = 4, string $email = 'mahasiswa@trk.local'): User
    {
        DB::table('roles')->updateOrInsert(
            ['id_role' => $roleId],
            [
                'nama_role' => $roleId === 4 ? 'mahasiswa' : 'dosen',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        return User::create([
            'id_role' => $roleId,
            'email' => $email,
            'password' => 'CurrentPassword123!',
            'status' => true,
        ]);
    }

    public function test_profile_otp_is_sent_to_the_configured_student_email(): void
    {
        Mail::fake();
        $student = $this->user();

        $this->actingAs($student)->postJson(route('mahasiswa.password.otp'), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk()
            ->assertJsonPath('expires_in_seconds', 300);

        Mail::assertSent(PasswordResetOtp::class, fn (PasswordResetOtp $mail) => $mail->hasTo(config('mahasiswa.otp_email')));

        $otp = DB::table('otp_codes')->first();
        $this->assertNotNull($otp);
        $this->assertTrue(Hash::check(Mail::sent(PasswordResetOtp::class)->first()->code, $otp->code_hash));
    }

    public function test_student_profile_shows_the_configured_otp_recipient(): void
    {
        $this->withoutVite();
        $student = $this->user();

        $this->actingAs($student)->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertSee('Kode OTP akan dikirim ke b************@mhs.polines.ac.id')
            ->assertSee('data-password-otp-url="'.route('mahasiswa.password.otp').'"', false)
            ->assertSee('data-password-verify-url="'.route('mahasiswa.password.verify').'"', false);
    }

    public function test_valid_profile_otp_updates_the_password_once(): void
    {
        Mail::fake();
        $student = $this->user();
        $this->actingAs($student)->postJson(route('mahasiswa.password.otp'), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $code = Mail::sent(PasswordResetOtp::class)->first()->code;
        $this->postJson(route('mahasiswa.password.verify'), [
            'otp' => $code,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diperbarui.');

        $this->assertTrue(Hash::check('NewPassword123!', $student->fresh()->password));
        $this->assertNotNull(DB::table('otp_codes')->value('consumed_at'));
    }

    public function test_profile_otp_is_limited_to_three_attempts(): void
    {
        Mail::fake();
        $student = $this->user();
        $this->actingAs($student)->postJson(route('mahasiswa.password.otp'), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $code = Mail::sent(PasswordResetOtp::class)->first()->code;
        $wrongCode = $code === '000000' ? '000001' : '000000';
        foreach (range(1, 3) as $attempt) {
            $this->postJson(route('mahasiswa.password.verify'), [
                'otp' => $wrongCode,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertUnprocessable();
        }

        $otp = DB::table('otp_codes')->first();
        $this->assertSame(3, (int) $otp->attempts);
        $this->assertNotNull($otp->consumed_at);
    }

    public function test_profile_otp_resend_is_limited_to_once_per_minute(): void
    {
        Mail::fake();
        $student = $this->user();
        $payload = [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ];

        $this->actingAs($student)->postJson(route('mahasiswa.password.otp'), $payload)->assertOk();
        $resend = $this->postJson(route('mahasiswa.password.otp'), $payload)->assertOk();
        $this->assertGreaterThanOrEqual(1, $resend->json('resend_after_seconds'));
        $this->assertLessThanOrEqual(60, $resend->json('resend_after_seconds'));
        Mail::assertSent(PasswordResetOtp::class, 1);

        $this->travel(61)->seconds();
        $this->postJson(route('mahasiswa.password.otp'), $payload)->assertOk();
        Mail::assertSent(PasswordResetOtp::class, 2);
        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_profile_password_otp_endpoints_are_student_only(): void
    {
        $lecturer = $this->user(3, 'lecturer@test.local');

        $this->actingAs($lecturer)->postJson(route('mahasiswa.password.otp'), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertForbidden();
    }
}
