<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Alur "Lupa sandi": menguji endpoint API (/api/v1/lupa-sandi/*) serta alur sesi web (PemulihanController).
 *
 * AMAN untuk database lokal: tes ini MEMAKSA SQLite di memori dan memblokir request HTTP liar
 * dengan Http::preventStrayRequests() agar tidak pernah menghubungi server lokal/eksternal.
 */
class PemulihanSandiTest extends TestCase
{
    private const SANDI_LAMA = 'SandiLama123';

    private int $idUser;

    private string $email = 'dosen@trk.ac.id';

    /** @var array<string, array{0: mixed, 1: mixed}> nilai env asli, dipulihkan setelah kelas tes selesai */
    private static array $envAsli = [];

    /** Paksa SQLite memori SEBELUM aplikasi dibuat, supaya MySQL lokal tidak pernah tersentuh. */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $k => $v) {
            self::$envAsli[$k] = [$_ENV[$k] ?? null, $_SERVER[$k] ?? null];
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }

    /** Kembalikan env seperti semula supaya kelas tes lain tidak ikut memakai SQLite. */
    public static function tearDownAfterClass(): void
    {
        foreach (self::$envAsli as $k => [$env, $server]) {
            if ($env === null) {
                unset($_ENV[$k]);
            } else {
                $_ENV[$k] = $env;
            }
            if ($server === null) {
                unset($_SERVER[$k]);
            } else {
                $_SERVER[$k] = $server;
            }
        }
        self::$envAsli = [];

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Ekstensi pdo_sqlite belum aktif di PHP.');
        }

        // Pengaman: jangan lanjut kalau bukan SQLite memori, dan pakai cache array agar throttle bersih tiap tes.
        $this->assertSame('sqlite', config('database.default'), 'Tes ini harus memakai SQLite, bukan database lokal.');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config(['cache.default' => 'array']);

        $this->buatTabel();
        $this->idUser = $this->buatUser($this->email, true);

        Mail::fake();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------- helper ----------

    private function buatTabel(): void
    {
        Schema::create('users', function ($t) {
            $t->increments('id_user');
            $t->unsignedInteger('id_role')->default(3);
            $t->string('email')->unique();
            $t->string('password');
            $t->boolean('status')->default(true);
            $t->string('foto_profil')->nullable();
            $t->string('remember_token', 100)->nullable();
            $t->timestamps();
        });

        Schema::create('otp_codes', function ($t) {
            $t->increments('id_otp_code');
            $t->unsignedInteger('id_user');
            $t->string('tujuan', 30);
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->timestamp('last_sent_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('personal_access_tokens', function ($t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    private function buatUser(string $email, bool $aktif): int
    {
        return (int) DB::table('users')->insertGetId([
            'id_role' => 3,
            'email' => $email,
            'password' => Hash::make(self::SANDI_LAMA),
            'status' => $aktif,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_user');
    }

    /** Minta OTP lewat endpoint API, lalu kembalikan kode 6 digit yang dikirim lewat email. */
    private function mintaOtp(?string $email = null): string
    {
        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => $email ?? $this->email])
            ->assertOk();

        $kode = null;
        Mail::assertSent(PasswordResetOtp::class, function ($mail) use (&$kode) {
            $kode = $mail->code;

            return true;
        });

        return (string) $kode;
    }

    private function otp(): object
    {
        return DB::table('otp_codes')->where('id_user', $this->idUser)->where('tujuan', 'lupa_sandi')->latest('id_otp_code')->first();
    }

    private function sandiTersimpan(): string
    {
        return (string) DB::table('users')->where('id_user', $this->idUser)->value('password');
    }

    // ---------- kirim OTP (API) ----------

    public function test_sending_otp_emails_a_six_digit_code_and_stores_only_its_hash(): void
    {
        $kode = $this->mintaOtp();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $kode);
        Mail::assertSent(PasswordResetOtp::class, fn ($m) => $m->hasTo($this->email));

        $otp = $this->otp();
        $this->assertSame('lupa_sandi', $otp->tujuan);
        $this->assertNotSame($kode, $otp->code_hash);
        $this->assertTrue(Hash::check($kode, $otp->code_hash));
        $this->assertSame(0, (int) $otp->attempts);
        $this->assertNull($otp->consumed_at);
    }

    public function test_unregistered_email_gets_the_same_response_but_no_email_is_sent(): void
    {
        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => 'tidak.ada@trk.ac.id'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('otp_codes')->count());
    }

    public function test_inactive_account_gets_no_otp(): void
    {
        $this->buatUser('nonaktif@trk.ac.id', false);

        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => 'nonaktif@trk.ac.id'])
            ->assertOk();

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('otp_codes')->count());
    }

    public function test_email_must_be_valid(): void
    {
        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => 'bukan-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_failed_email_delivery_shows_a_message_instead_of_a_500(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new TransportException('SMTP mati'));

        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => $this->email])
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        // OTP yang gagal terkirim tidak boleh tetap bisa dipakai, dan tidak boleh meninggalkan jeda.
        $this->assertNotNull($this->otp()->consumed_at);
        $this->assertNull($this->otp()->last_sent_at, 'Pengiriman gagal tidak boleh memblokir percobaan berikutnya.');
    }

    public function test_otp_of_another_purpose_is_never_touched(): void
    {
        DB::table('otp_codes')->insert([
            'id_user' => $this->idUser,
            'tujuan' => 'ganti_sandi',
            'code_hash' => Hash::make('111111'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mintaOtp();

        $profil = DB::table('otp_codes')->where('tujuan', 'ganti_sandi')->first();
        $this->assertTrue(Hash::check('111111', $profil->code_hash), 'OTP alur profil tertimpa.');
        $this->assertNull($profil->consumed_at);
        $this->assertSame(2, DB::table('otp_codes')->count());
    }

    // ---------- kirim ulang (API) ----------

    public function test_resend_during_the_cooldown_sends_nothing_but_answers_like_a_normal_request(): void
    {
        $kode = $this->mintaOtp();

        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => $this->email])
            ->assertOk()
            ->assertJsonPath('success', true);

        Mail::assertSentCount(1);
        $this->assertTrue(Hash::check($kode, $this->otp()->code_hash), 'Kode lama tidak boleh berganti saat jeda.');
    }

    public function test_cooldown_response_does_not_reveal_whether_the_email_is_registered(): void
    {
        $this->mintaOtp();

        $terdaftar = $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => $this->email]);
        $tidakAda = $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => 'tidak.ada@trk.ac.id']);

        $this->assertSame($tidakAda->status(), $terdaftar->status());
        $this->assertSame($tidakAda->json(), $terdaftar->json());
    }

    public function test_cooldown_still_applies_after_the_code_was_locked_by_wrong_attempts(): void
    {
        $kode = $this->mintaOtp();
        $salah = $kode === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $salah]);
        }
        $this->assertNotNull($this->otp()->consumed_at);

        // Kode sudah hangus, tapi baru saja dikirim: tidak boleh langsung dikirim kode baru.
        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => $this->email])->assertOk();
        Mail::assertSentCount(1);

        // Setelah 61 detik baru boleh.
        Carbon::setTestNow(now()->addSeconds(61));
        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => $this->email])->assertOk();
        Mail::assertSentCount(2);
    }

    public function test_resend_works_after_the_cooldown_and_replaces_the_code(): void
    {
        $lama = $this->mintaOtp();

        Carbon::setTestNow(now()->addSeconds(61));
        $this->postJson('/api/v1/lupa-sandi/kirim-otp', ['email' => $this->email])
            ->assertOk();

        Mail::assertSentCount(2);
        $this->assertFalse(Hash::check($lama, $this->otp()->code_hash), 'Kode lama seharusnya sudah diganti.');
    }

    // ---------- verifikasi OTP (API) ----------

    public function test_correct_otp_marks_otp_as_verified(): void
    {
        $kode = $this->mintaOtp();

        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $kode])
            ->assertOk()
            ->assertJsonPath('data.otp_id', $this->otp()->id_otp_code);

        $this->assertNotNull($this->otp()->verified_at);
    }

    public function test_wrong_otp_counts_an_attempt(): void
    {
        $kode = $this->mintaOtp();
        $salah = $kode === '000000' ? '111111' : '000000';

        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $salah])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('otp');

        $this->assertSame(1, (int) $this->otp()->attempts);
        $this->assertNull($this->otp()->verified_at);
    }

    public function test_three_wrong_attempts_lock_the_code_even_for_the_right_one(): void
    {
        $kode = $this->mintaOtp();
        $salah = $kode === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $salah]);
        }

        $this->assertNotNull($this->otp()->consumed_at);

        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $kode])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('otp');
    }

    public function test_expired_otp_is_rejected(): void
    {
        $kode = $this->mintaOtp();

        Carbon::setTestNow(now()->addMinutes(6));

        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $kode])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('otp');

        $this->assertNull($this->otp()->verified_at);
    }

    public function test_an_already_verified_code_cannot_be_verified_again(): void
    {
        $kode = $this->mintaOtp();
        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $kode])->assertOk();
        $berlakuSampai = $this->otp()->expires_at;

        Carbon::setTestNow(now()->addMinute());
        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $kode])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('otp');

        $this->assertSame($berlakuSampai, $this->otp()->expires_at, 'Jendela waktu tidak boleh diperpanjang lagi.');
    }

    public function test_verification_is_rate_limited_per_email_not_for_everyone(): void
    {
        $this->mintaOtp();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => '000000']);
        }

        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => '000000'])
            ->assertStatus(429);

        // Email lain (pengguna lain di server yang sama) tidak ikut terblokir.
        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => 'orang.lain@trk.ac.id', 'otp' => '000000'])
            ->assertUnprocessable();
    }

    public function test_otp_must_be_six_digits(): void
    {
        $this->mintaOtp();

        $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => '12ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('otp');
    }

    public function test_verifying_without_starting_the_flow_is_a_404_on_web(): void
    {
        $this->post(route('password.otp'), ['otp' => '123456'])->assertNotFound();
    }

    // ---------- sandi baru (API) ----------

    private function sampaiTerverifikasi(): int
    {
        $kode = $this->mintaOtp();
        $res = $this->postJson('/api/v1/lupa-sandi/verifikasi', ['email' => $this->email, 'otp' => $kode])
            ->assertOk();

        return (int) $res->json('data.otp_id');
    }

    public function test_new_password_is_saved_otp_is_consumed_and_api_tokens_are_revoked(): void
    {
        $user = User::query()->find($this->idUser);
        $user->createToken('perangkat-lama');
        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        $otpId = $this->sampaiTerverifikasi();

        $this->postJson('/api/v1/lupa-sandi/simpan', [
            'email' => $this->email,
            'otp_id' => $otpId,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertOk();

        $this->assertTrue(Hash::check('SandiBaru123', $this->sandiTersimpan()));
        $this->assertFalse(Hash::check(self::SANDI_LAMA, $this->sandiTersimpan()));
        $this->assertNotNull($this->otp()->consumed_at);
        $this->assertSame(0, DB::table('personal_access_tokens')->count(), 'Token API lama harus dicabut.');
    }

    public function test_too_short_or_mismatched_passwords_are_rejected(): void
    {
        $otpId = $this->sampaiTerverifikasi();

        foreach ([
            ['password' => 'Pendek1', 'password_confirmation' => 'Pendek1'],         // kurang dari 8 karakter
            ['password' => 'SandiBaru123', 'password_confirmation' => 'Beda12345'],  // konfirmasi beda
            ['password' => '', 'password_confirmation' => ''],                        // kosong
        ] as $input) {
            $this->postJson('/api/v1/lupa-sandi/simpan', array_merge([
                'email' => $this->email,
                'otp_id' => $otpId,
            ], $input))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('password');
        }

        $this->assertTrue(Hash::check(self::SANDI_LAMA, $this->sandiTersimpan()), 'Sandi tidak boleh berubah.');
        $this->assertNull($this->otp()->consumed_at);
    }

    public function test_a_password_of_only_letters_is_accepted_because_the_rule_is_min_8(): void
    {
        $otpId = $this->sampaiTerverifikasi();

        $this->postJson('/api/v1/lupa-sandi/simpan', [
            'email' => $this->email,
            'otp_id' => $otpId,
            'password' => 'hurufsajaya',
            'password_confirmation' => 'hurufsajaya',
        ])->assertOk();

        $this->assertTrue(Hash::check('hurufsajaya', $this->sandiTersimpan()));
    }

    public function test_verifying_the_otp_gives_a_separate_window_to_set_the_new_password(): void
    {
        $this->sampaiTerverifikasi();

        $this->assertTrue(
            Carbon::parse($this->otp()->expires_at)->greaterThan(now()->addMinutes(6)),
            'Setelah OTP benar, batas waktu harus diperpanjang (sekitar 7 menit).'
        );
    }

    public function test_password_can_still_be_saved_six_minutes_after_the_otp_was_verified(): void
    {
        $otpId = $this->sampaiTerverifikasi();

        // Sudah lewat 5 menit dari OTP dikirim, tapi masih dalam jendela sandi baru.
        Carbon::setTestNow(now()->addMinutes(6));

        $this->postJson('/api/v1/lupa-sandi/simpan', [
            'email' => $this->email,
            'otp_id' => $otpId,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertOk();

        $this->assertTrue(Hash::check('SandiBaru123', $this->sandiTersimpan()));
    }

    public function test_password_cannot_be_saved_after_the_window_has_passed(): void
    {
        $otpId = $this->sampaiTerverifikasi();

        Carbon::setTestNow(now()->addMinutes(8));

        $this->postJson('/api/v1/lupa-sandi/simpan', [
            'email' => $this->email,
            'otp_id' => $otpId,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check(self::SANDI_LAMA, $this->sandiTersimpan()));
    }

    public function test_the_same_otp_cannot_reset_the_password_twice(): void
    {
        $otpId = $this->sampaiTerverifikasi();

        $this->postJson('/api/v1/lupa-sandi/simpan', [
            'email' => $this->email,
            'otp_id' => $otpId,
            'password' => 'SandiBaru123',
            'password_confirmation' => 'SandiBaru123',
        ])->assertOk();

        $this->postJson('/api/v1/lupa-sandi/simpan', [
            'email' => $this->email,
            'otp_id' => $otpId,
            'password' => 'Lainnya12345',
            'password_confirmation' => 'Lainnya12345',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('SandiBaru123', $this->sandiTersimpan()));
    }

    // ---------- alur sesi web (PemulihanController dengan Http::fake) ----------

    public function test_web_flow_transitions_through_all_steps_via_api(): void
    {
        Http::fake([
            '*/lupa-sandi/kirim-otp' => Http::response(['success' => true, 'message' => 'Terkirim', 'data' => null], 200),
            '*/lupa-sandi/verifikasi' => Http::response(['success' => true, 'message' => 'Valid', 'data' => ['otp_id' => 99, 'batas_menit' => 7]], 200),
            '*/lupa-sandi/simpan' => Http::response(['success' => true, 'message' => 'Tersimpan', 'data' => null], 200),
        ]);

        $this->post(route('password.email'), ['email' => $this->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('pemulihan.step', 2)
            ->assertSessionHas('pemulihan.otp_berlaku_sampai');

        $this->post(route('password.otp'), ['otp' => '123456'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('pemulihan.step', 3)
            ->assertSessionHas('pemulihan.otp_id', 99);

        $this->post(route('password.simpan'), ['password' => 'SandiBaru123', 'password_confirmation' => 'SandiBaru123'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('pemulihan.step', 4);
    }

    public function test_step_three_page_sends_you_back_to_step_one_when_the_window_has_passed(): void
    {
        $this->withSession([
            'pemulihan' => [
                'step' => 3,
                'email' => $this->email,
                'otp_id' => 1,
                'berlaku_sampai' => now()->subMinute()->timestamp,
            ],
        ])
            ->get(route('password.request'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertNull(session('pemulihan'));
    }

    public function test_saving_a_password_without_verifying_otp_is_a_404(): void
    {
        $this->withSession([
            'pemulihan' => [
                'step' => 2,
                'email' => $this->email,
                'terakhir_kirim' => now()->timestamp,
            ],
        ])
            ->post(route('password.simpan'), ['password' => 'SandiBaru123', 'password_confirmation' => 'SandiBaru123'])
            ->assertNotFound();
    }

    public function test_step_two_page_sends_you_back_to_step_one_when_the_otp_has_expired(): void
    {
        $this->withSession([
            'pemulihan' => [
                'step' => 2,
                'email' => $this->email,
                'terakhir_kirim' => now()->subMinutes(6)->timestamp,
                'otp_berlaku_sampai' => now()->subMinute()->timestamp,
            ],
        ])
            ->get(route('password.request'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertNull(session('pemulihan'));
    }

    public function test_submitting_an_expired_otp_does_not_call_the_api(): void
    {
        Http::fake();

        $this->withSession([
            'pemulihan' => [
                'step' => 2,
                'email' => $this->email,
                'terakhir_kirim' => now()->subMinutes(6)->timestamp,
                'otp_berlaku_sampai' => now()->subMinute()->timestamp,
            ],
        ])
            ->post(route('password.otp'), ['otp' => '123456'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        Http::assertNothingSent();
    }

    public function test_an_old_step_two_session_without_an_expiry_time_starts_over(): void
    {
        $this->withSession([
            'pemulihan' => ['step' => 2, 'email' => $this->email],
        ])
            ->get(route('password.request'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        $this->assertNull(session('pemulihan'));
    }

    public function test_opening_the_page_with_mulai_always_starts_at_step_one(): void
    {
        $this->withoutVite();

        $this->withSession([
            'pemulihan' => [
                'step' => 2,
                'email' => $this->email,
                'otp_berlaku_sampai' => now()->addMinutes(3)->timestamp,
            ],
        ])
            ->get(route('password.request', ['mulai' => 1]))
            ->assertOk()
            ->assertViewHas('step', 1);
    }
}
