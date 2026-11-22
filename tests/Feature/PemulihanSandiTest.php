<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * Alur "Lupa sandi" dari halaman login (PemulihanController): kirim OTP, verifikasi OTP, simpan sandi baru.
 *
 * AMAN untuk database lokal: tes ini MEMAKSA SQLite di memori dan membuat sendiri tabel yang dibutuhkan
 * (users, otp_codes, personal_access_tokens). Bila koneksi bukan SQLite, tes berhenti sebelum menyentuh database apa pun.
 * Email ditiru dengan Mail::fake(), jadi tidak ada email sungguhan yang terkirim.
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

        // Pengaman: jangan lanjut kalau bukan SQLite memori.
        $this->assertSame('sqlite', config('database.default'), 'Tes ini harus memakai SQLite, bukan database lokal.');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $this->buatTabel();
        $this->idUser = $this->buatUser($this->email, true);

        Mail::fake();
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

    /** Minta OTP dari halaman lupa sandi, lalu kembalikan kode 6 digit yang dikirim lewat email. */
    private function mintaOtp(?string $email = null): string
    {
        $this->post(route('password.email'), ['email' => $email ?? $this->email])
            ->assertRedirect(route('password.request'));

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

    // ---------- kirim OTP ----------

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
        $this->assertSame(2, session('pemulihan.step'));
    }

    public function test_unregistered_email_gets_the_same_response_but_no_email_is_sent(): void
    {
        $this->post(route('password.email'), ['email' => 'tidak.ada@trk.ac.id'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        $this->assertSame(2, session('pemulihan.step'));
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('otp_codes')->count());
    }

    public function test_inactive_account_gets_no_otp(): void
    {
        $this->buatUser('nonaktif@trk.ac.id', false);

        $this->post(route('password.email'), ['email' => 'nonaktif@trk.ac.id'])
            ->assertRedirect(route('password.request'));

        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('otp_codes')->count());
    }

    public function test_email_must_be_valid(): void
    {
        $this->post(route('password.email'), ['email' => 'bukan-email'])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_failed_email_delivery_shows_a_message_instead_of_a_500(): void
    {
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new TransportException('SMTP mati'));

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $this->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');

        // OTP yang gagal terkirim tidak boleh tetap bisa dipakai.
        $this->assertNotNull($this->otp()->consumed_at);
    }

    public function test_otp_of_another_purpose_is_never_touched(): void
    {
        DB::table('otp_codes')->insert([
            'id_user' => $this->idUser,
            'tujuan' => 'ganti_sandi_profil',
            'code_hash' => Hash::make('111111'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->mintaOtp();

        $profil = DB::table('otp_codes')->where('tujuan', 'ganti_sandi_profil')->first();
        $this->assertTrue(Hash::check('111111', $profil->code_hash), 'OTP alur profil tertimpa.');
        $this->assertNull($profil->consumed_at);
        $this->assertSame(2, DB::table('otp_codes')->count());
    }

    // ---------- kirim ulang ----------

    public function test_resend_is_blocked_during_the_cooldown(): void
    {
        $this->mintaOtp();

        $this->from(route('password.request'))
            ->post(route('password.kirim-ulang'))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('otp');

        Mail::assertSentCount(1);
    }

    public function test_resend_works_after_the_cooldown_and_replaces_the_code(): void
    {
        $lama = $this->mintaOtp();

        Carbon::setTestNow(now()->addSeconds(61));
        $this->post(route('password.kirim-ulang'))->assertRedirect(route('password.request'));

        Mail::assertSentCount(2);
        $this->assertFalse(Hash::check($lama, $this->otp()->code_hash), 'Kode lama seharusnya sudah diganti.');
    }

    // ---------- verifikasi OTP ----------

    public function test_correct_otp_opens_step_three(): void
    {
        $kode = $this->mintaOtp();

        $this->post(route('password.otp'), ['otp' => $kode])->assertRedirect(route('password.request'));

        $this->assertSame(3, session('pemulihan.step'));
        $this->assertNotNull($this->otp()->verified_at);
    }

    public function test_wrong_otp_counts_an_attempt(): void
    {
        $kode = $this->mintaOtp();
        $salah = $kode === '000000' ? '111111' : '000000';

        $this->from(route('password.request'))
            ->post(route('password.otp'), ['otp' => $salah])
            ->assertSessionHasErrors('otp');

        $this->assertSame(1, (int) $this->otp()->attempts);
        $this->assertNull($this->otp()->verified_at);
        $this->assertSame(2, session('pemulihan.step'));
    }

    public function test_three_wrong_attempts_lock_the_code_even_for_the_right_one(): void
    {
        $kode = $this->mintaOtp();
        $salah = $kode === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 3; $i++) {
            $this->from(route('password.request'))->post(route('password.otp'), ['otp' => $salah]);
        }

        $this->assertNotNull($this->otp()->consumed_at);

        $this->from(route('password.request'))
            ->post(route('password.otp'), ['otp' => $kode])
            ->assertSessionHasErrors('otp');

        $this->assertSame(2, session('pemulihan.step'));
    }

    public function test_expired_otp_is_rejected(): void
    {
        $kode = $this->mintaOtp();

        Carbon::setTestNow(now()->addMinutes(6));

        $this->from(route('password.request'))
            ->post(route('password.otp'), ['otp' => $kode])
            ->assertSessionHasErrors('otp');

        $this->assertNull($this->otp()->verified_at);
    }

    public function test_otp_must_be_six_digits(): void
    {
        $this->mintaOtp();

        $this->from(route('password.request'))
            ->post(route('password.otp'), ['otp' => '12ab'])
            ->assertSessionHasErrors('otp');
    }

    public function test_verifying_without_starting_the_flow_is_a_404(): void
    {
        $this->post(route('password.otp'), ['otp' => '123456'])->assertNotFound();
    }

    // ---------- sandi baru ----------

    private function sampaiLangkahTiga(): void
    {
        $kode = $this->mintaOtp();
        $this->post(route('password.otp'), ['otp' => $kode]);
    }

    public function test_new_password_is_saved_otp_is_consumed_and_api_tokens_are_revoked(): void
    {
        $user = User::query()->find($this->idUser);
        $user->createToken('perangkat-lama');
        $this->assertSame(1, DB::table('personal_access_tokens')->count());

        $this->sampaiLangkahTiga();

        $this->post(route('password.simpan'), ['password' => 'SandiBaru123', 'password_confirmation' => 'SandiBaru123'])
            ->assertRedirect(route('password.request'));

        $this->assertSame(4, session('pemulihan.step'));
        $this->assertTrue(Hash::check('SandiBaru123', $this->sandiTersimpan()));
        $this->assertFalse(Hash::check(self::SANDI_LAMA, $this->sandiTersimpan()));
        $this->assertNotNull($this->otp()->consumed_at);
        $this->assertSame(0, DB::table('personal_access_tokens')->count(), 'Token API lama harus dicabut.');
    }

    public function test_too_short_or_mismatched_passwords_are_rejected(): void
    {
        $this->sampaiLangkahTiga();

        foreach ([
            ['password' => 'Pendek1', 'password_confirmation' => 'Pendek1'],         // kurang dari 8 karakter
            ['password' => 'SandiBaru123', 'password_confirmation' => 'Beda12345'],  // konfirmasi beda
            ['password' => '', 'password_confirmation' => ''],                        // kosong
        ] as $input) {
            $this->from(route('password.request'))
                ->post(route('password.simpan'), $input)
                ->assertSessionHasErrors('password');
        }

        $this->assertTrue(Hash::check(self::SANDI_LAMA, $this->sandiTersimpan()), 'Sandi tidak boleh berubah.');
        $this->assertNull($this->otp()->consumed_at);
    }

    public function test_a_password_of_only_letters_is_accepted_because_the_rule_is_min_8(): void
    {
        $this->sampaiLangkahTiga();

        $this->post(route('password.simpan'), ['password' => 'hurufsajaya', 'password_confirmation' => 'hurufsajaya'])
            ->assertRedirect(route('password.request'));

        $this->assertTrue(Hash::check('hurufsajaya', $this->sandiTersimpan()));
    }

    public function test_saving_a_password_without_verifying_otp_is_a_404(): void
    {
        $this->mintaOtp(); // baru langkah 2

        $this->post(route('password.simpan'), ['password' => 'SandiBaru123', 'password_confirmation' => 'SandiBaru123'])
            ->assertNotFound();

        $this->assertTrue(Hash::check(self::SANDI_LAMA, $this->sandiTersimpan()));
    }

    public function test_the_same_otp_cannot_reset_the_password_twice(): void
    {
        $this->sampaiLangkahTiga();
        $this->post(route('password.simpan'), ['password' => 'SandiBaru123', 'password_confirmation' => 'SandiBaru123']);

        // Simpan sesi langkah 3 lama (replay): OTP sudah consumed, jadi harus ditolak.
        $this->withSession(['pemulihan' => ['step' => 3, 'email' => $this->email, 'otp_id' => $this->otp()->id_otp_code]])
            ->from(route('password.request'))
            ->post(route('password.simpan'), ['password' => 'Lainnya12345', 'password_confirmation' => 'Lainnya12345'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('SandiBaru123', $this->sandiTersimpan()));
    }
}
