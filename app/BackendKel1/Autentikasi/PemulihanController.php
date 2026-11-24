<?php

namespace App\BackendKel1\Autentikasi;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtp;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PemulihanController extends Controller
{
    private const TUJUAN_LUPA_SANDI = 'lupa_sandi';

    private const TUJUAN_PROFIL = 'ganti_sandi';

    private const TUJUAN_GANTI_EMAIL = 'ganti_email';

    private const OTP_MENIT = 5;

    private const SANDI_BARU_MENIT = 7;

    private const OTP_MAKS_PERCOBAAN = 3;

    private const OTP_JEDA_KIRIM_DETIK = 60;

    // ALUR 1: LUPA SANDI (PUBLIK / BELUM LOGIN)
    public function kirimOtp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        if (! $this->emailDeliveryAvailable()) {
            return ApiResponse::gagal(
                'Layanan email belum dikonfigurasi. Hubungi administrator.',
                503,
                ['email' => ['Layanan email belum dikonfigurasi. Hubungi administrator.']]
            );
        }

        $email = Str::lower(trim($data['email']));
        $user = User::query()->where('email', $email)->where('status', true)->first();

        if ($user) {
            $sisaJeda = $this->sisaJedaKirim($user, self::TUJUAN_LUPA_SANDI);
            if ($sisaJeda > 0) {
                $pesan = "Tunggu {$sisaJeda} detik sebelum meminta kode baru.";

                return ApiResponse::gagal($pesan, 422, ['otp' => [$pesan]]);
            }

            try {
                $this->sendOtp($user, self::TUJUAN_LUPA_SANDI, $this->otpRecipient($user));
            } catch (TransportExceptionInterface $e) {
                Log::error('Gagal mengirim OTP pemulihan via API: '.$e->getMessage());
                $pesan = 'Email gagal dikirim. Coba lagi beberapa saat atau hubungi administrator.';

                return ApiResponse::gagal($pesan, 500, ['email' => [$pesan]]);
            }
        }

        return ApiResponse::ok(null, 'Jika email terdaftar, kode OTP telah dikirim. Periksa kotak masuk atau folder spam.');
    }

    public function verifikasi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'otp' => ['required', 'digits:6'],
        ], [
            'email.required' => 'Email wajib diisi.',
            'otp.required' => 'Masukkan 6 digit kode OTP.',
            'otp.digits' => 'Kode OTP harus 6 digit angka.',
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::query()->where('email', $email)->where('status', true)->first();

        if (! $user) {
            return ApiResponse::gagal('Kode OTP tidak valid atau sudah kedaluwarsa.', 422, [
                'otp' => ['Kode OTP tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        return $this->prosesVerifikasiOtp($user, (string) $data['otp'], self::TUJUAN_LUPA_SANDI);
    }

    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'otp_id' => ['required', 'integer'],
            'password' => ['required', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Sesi pemulihan tidak valid. Mulai kembali.',
            'otp_id.required' => 'Sesi pemulihan tidak valid. Mulai kembali.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::query()->where('email', $email)->where('status', true)->first();

        if (! $user) {
            return ApiResponse::gagal('Akun tidak ditemukan atau tidak aktif. Mulai kembali.', 422, [
                'password' => ['Akun tidak ditemukan atau tidak aktif. Mulai kembali.'],
            ]);
        }

        $gagal = $this->simpanSandiDenganOtp($user, (int) $data['otp_id'], $data['password'], self::TUJUAN_LUPA_SANDI);
        if ($gagal !== null) {
            return $gagal;
        }

        return ApiResponse::ok(null, 'Kata sandi berhasil diperbarui.');
    }

    // ALUR 2A: GANTI SANDI PROFIL (VERIFIKASI SANDI LAMA)

    public function gantiSandiProfil(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return ApiResponse::gagal('Tidak terautentikasi.', 401);
        }

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return ApiResponse::gagal('Kata sandi saat ini tidak sesuai.', 422, [
                'current_password' => ['Kata sandi saat ini tidak sesuai.'],
            ]);
        }

        $tokenBaru = $this->perbaruiPasswordDanToken($user, $data['password']);

        return ApiResponse::ok(['token' => $tokenBaru], 'Kata sandi berhasil diubah.');
    }

    // ALUR 2B: GANTI SANDI PROFIL (DENGAN OTP EMAIL)

    public function kirimOtpProfil(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return ApiResponse::gagal('Tidak terautentikasi.', 401);
        }

        if (! $this->emailDeliveryAvailable()) {
            return ApiResponse::gagal('Layanan email belum dikonfigurasi. Hubungi administrator.', 503);
        }

        $sisaJeda = $this->sisaJedaKirim($user, self::TUJUAN_PROFIL);
        if ($sisaJeda > 0) {
            $pesan = "Tunggu {$sisaJeda} detik sebelum meminta kode baru.";

            return ApiResponse::gagal($pesan, 422, ['otp' => [$pesan]]);
        }

        try {
            $this->sendOtp($user, self::TUJUAN_PROFIL, $this->otpRecipient($user));
        } catch (TransportExceptionInterface $e) {
            Log::error('Gagal mengirim OTP ganti sandi profil via API: '.$e->getMessage());

            return ApiResponse::gagal('Email gagal dikirim. Coba lagi beberapa saat.', 500);
        }

        return ApiResponse::ok(null, 'Kode OTP telah dikirim ke email Anda.');
    }

    public function verifikasiOtpProfil(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return ApiResponse::gagal('Tidak terautentikasi.', 401);
        }

        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Masukkan 6 digit kode OTP.',
            'otp.digits' => 'Kode OTP harus 6 digit angka.',
        ]);

        return $this->prosesVerifikasiOtp($user, (string) $data['otp'], self::TUJUAN_PROFIL);
    }

    public function simpanSandiProfilOtp(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return ApiResponse::gagal('Tidak terautentikasi.', 401);
        }

        $data = $request->validate([
            'otp_id' => ['required', 'integer'],
            'password' => ['required', 'min:8', 'confirmed'],
        ], [
            'otp_id.required' => 'Sesi verifikasi OTP tidak valid.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        $gagal = $this->simpanSandiDenganOtp($user, (int) $data['otp_id'], $data['password'], self::TUJUAN_PROFIL, false);
        if ($gagal !== null) {
            return $gagal;
        }

        $tokenBaru = $this->perbaruiPasswordDanToken($user, $data['password']);

        return ApiResponse::ok(['token' => $tokenBaru], 'Kata sandi berhasil diubah.');
    }

    public function kirimOtpEmailProfil(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return ApiResponse::gagal('Tidak terautentikasi.', 401);
        }

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->getKey(), 'id_user')],
        ], [
            'email.required' => 'Email baru wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email tersebut sudah digunakan akun lain.',
        ]);

        if ($data['email'] === Str::lower($user->email)) {
            return ApiResponse::gagal('Email baru harus berbeda dari email saat ini.', 422, [
                'email' => ['Email baru harus berbeda dari email saat ini.'],
            ]);
        }

        if (! $this->emailDeliveryAvailable()) {
            return ApiResponse::gagal('Layanan email belum dikonfigurasi. Hubungi administrator.', 503);
        }

        $sisaJeda = $this->sisaJedaKirim($user, self::TUJUAN_GANTI_EMAIL);
        if ($sisaJeda > 0) {
            $pesan = "Tunggu {$sisaJeda} detik sebelum meminta kode baru.";

            return ApiResponse::gagal($pesan, 422, ['otp' => [$pesan]]);
        }

        try {
            $this->sendOtp($user, self::TUJUAN_GANTI_EMAIL, $data['email']);
        } catch (TransportExceptionInterface $e) {
            Log::error('Gagal mengirim OTP perubahan email profil via API: '.$e->getMessage());

            return ApiResponse::gagal('Email gagal dikirim. Coba lagi beberapa saat.', 500);
        }

        return ApiResponse::ok(null, 'Kode verifikasi telah dikirim ke email baru.');
    }

    public function verifikasiEmailProfil(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return ApiResponse::gagal('Tidak terautentikasi.', 401);
        }

        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Masukkan 6 digit kode OTP.',
            'otp.digits' => 'Kode OTP harus 6 digit angka.',
        ]);

        $error = null;
        $emailBaru = null;

        DB::transaction(function () use ($user, $data, &$error, &$emailBaru) {
            $otp = DB::table('otp_codes')
                ->where('id_user', $user->getKey())
                ->where('tujuan', self::TUJUAN_GANTI_EMAIL)
                ->whereNull('consumed_at')
                ->latest('id_otp_code')
                ->lockForUpdate()
                ->first();

            if (! $otp || Carbon::parse($otp->expires_at)->isPast()) {
                $error = 'Kode OTP tidak valid atau sudah kedaluwarsa. Kirim ulang OTP.';

                return;
            }

            if ((int) $otp->attempts >= self::OTP_MAKS_PERCOBAAN) {
                $error = 'Batas percobaan tercapai. Kirim ulang OTP.';

                return;
            }

            if (! Hash::check((string) $data['otp'], $otp->code_hash)) {
                $attempts = (int) $otp->attempts + 1;
                DB::table('otp_codes')->where('id_otp_code', $otp->id_otp_code)->update([
                    'attempts' => $attempts,
                    'consumed_at' => $attempts >= self::OTP_MAKS_PERCOBAAN ? now() : null,
                    'updated_at' => now(),
                ]);
                $error = $attempts >= self::OTP_MAKS_PERCOBAAN
                    ? 'Batas percobaan tercapai. Kirim ulang OTP.'
                    : 'Kode OTP tidak valid atau sudah kedaluwarsa.';

                return;
            }

            $emailBaru = $otp->recipient_email;
            if (! $emailBaru || DB::table('users')->where('email', $emailBaru)
                ->where('id_user', '!=', $user->getKey())->exists()) {
                $error = 'Email sudah digunakan akun lain. Mulai kembali dengan email yang berbeda.';

                return;
            }

            $updated = DB::table('users')->where('id_user', $user->getKey())
                ->where('email', $user->email)
                ->update(['email' => $emailBaru, 'updated_at' => now()]);

            if ($updated !== 1) {
                $error = 'Email akun berubah. Muat ulang halaman lalu coba kembali.';

                return;
            }

            DB::table('otp_codes')->where('id_otp_code', $otp->id_otp_code)
                ->update(['verified_at' => now(), 'consumed_at' => now(), 'updated_at' => now()]);
        });

        if ($error !== null) {
            return ApiResponse::gagal($error, 422, ['otp' => [$error]]);
        }

        return ApiResponse::ok(['email' => $emailBaru], 'Email akun berhasil diperbarui.');
    }

    // HELPER INTERNAL OTP & TOKEN

    private function prosesVerifikasiOtp(User $user, string $kodeInput, string $tujuan): JsonResponse
    {
        $otp = DB::table('otp_codes')
            ->where('id_user', $user->getKey())
            ->where('tujuan', $tujuan)
            ->whereNull('consumed_at')
            ->latest('id_otp_code')
            ->first();

        if (! $otp || Carbon::parse($otp->expires_at)->isPast()) {
            if ($otp) {
                DB::table('otp_codes')->where('id_otp_code', $otp->id_otp_code)
                    ->update(['consumed_at' => now(), 'updated_at' => now()]);
            }

            $pesan = 'Kode OTP tidak valid atau sudah kedaluwarsa. Kirim ulang OTP.';

            return ApiResponse::gagal($pesan, 422, ['otp' => [$pesan]]);
        }

        if ((int) $otp->attempts >= self::OTP_MAKS_PERCOBAAN) {
            $pesan = 'Batas percobaan tercapai. Kirim ulang OTP.';

            return ApiResponse::gagal($pesan, 422, ['otp' => [$pesan]]);
        }

        if (! Hash::check($kodeInput, $otp->code_hash)) {
            DB::table('otp_codes')->where('id_otp_code', $otp->id_otp_code)
                ->increment('attempts', 1, ['updated_at' => now()]);

            $attempts = (int) DB::table('otp_codes')
                ->where('id_otp_code', $otp->id_otp_code)->value('attempts');

            if ($attempts >= self::OTP_MAKS_PERCOBAAN) {
                DB::table('otp_codes')->where('id_otp_code', $otp->id_otp_code)
                    ->update(['consumed_at' => now(), 'updated_at' => now()]);
            }

            $pesan = $attempts >= self::OTP_MAKS_PERCOBAAN
                ? 'Batas percobaan tercapai. Kirim ulang OTP.'
                : 'Kode OTP tidak valid atau sudah kedaluwarsa.';

            return ApiResponse::gagal($pesan, 422, ['otp' => [$pesan]]);
        }

        DB::table('otp_codes')->where('id_otp_code', $otp->id_otp_code)
            ->update([
                'verified_at' => now(),
                'expires_at' => now()->addMinutes(self::SANDI_BARU_MENIT),
                'updated_at' => now(),
            ]);

        return ApiResponse::ok([
            'otp_id' => (int) $otp->id_otp_code,
            'batas_menit' => self::SANDI_BARU_MENIT,
        ], 'Kode OTP valid.');
    }

    private function simpanSandiDenganOtp(User $user, int $otpId, string $passwordBaru, string $tujuan, bool $updatePassword = true): ?JsonResponse
    {
        $errorPesan = null;

        DB::transaction(function () use ($user, $otpId, $passwordBaru, $tujuan, $updatePassword, &$errorPesan) {
            $otp = DB::table('otp_codes')->where('id_otp_code', $otpId)
                ->lockForUpdate()->first();

            if (! $otp
                || (int) $otp->id_user !== (int) $user->getKey()
                || $otp->tujuan !== $tujuan
                || $otp->consumed_at !== null
                || $otp->verified_at === null
                || Carbon::parse($otp->expires_at)->isPast()) {
                $errorPesan = 'Sesi pemulihan tidak valid atau sudah kedaluwarsa. Mulai kembali.';

                return;
            }

            $consumed = DB::table('otp_codes')->where('id_otp_code', $otpId)
                ->whereNull('consumed_at')->update(['consumed_at' => now(), 'updated_at' => now()]);

            if ($consumed !== 1) {
                $errorPesan = 'Kode pemulihan sudah digunakan. Mulai kembali.';

                return;
            }

            if ($updatePassword) {
                $user->forceFill([
                    'password' => Hash::make($passwordBaru),
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            }
        });

        if ($errorPesan !== null) {
            return ApiResponse::gagal($errorPesan, 422, ['password' => [$errorPesan]]);
        }

        return null;
    }

    private function perbaruiPasswordDanToken(User $user, string $passwordBaru): string
    {
        $user->forceFill([
            'password' => Hash::make($passwordBaru),
            'remember_token' => Str::random(60),
        ])->save();

        // Cabut semua token lama lalu terbitkan token baru untuk sesi aktif saat ini.
        $user->tokens()->delete();

        return $user->createToken('web')->plainTextToken;
    }

    private function sisaJedaKirim(User $user, string $tujuan): int
    {
        $otp = DB::table('otp_codes')
            ->where('id_user', $user->getKey())
            ->where('tujuan', $tujuan)
            ->whereNull('consumed_at')
            ->latest('id_otp_code')
            ->first();

        if (! $otp || ! $otp->last_sent_at) {
            return 0;
        }

        $batas = Carbon::parse($otp->last_sent_at)->addSeconds(self::OTP_JEDA_KIRIM_DETIK);

        return $batas->isFuture() ? (int) now()->diffInSeconds($batas) : 0;
    }

    private function sendOtp(User $user, string $tujuan, ?string $recipientEmail = null): void
    {
        $recipientEmail ??= $user->email;
        $otp = DB::table('otp_codes')
            ->where('id_user', $user->getKey())
            ->where('tujuan', $tujuan)
            ->whereNull('consumed_at')
            ->latest('id_otp_code')
            ->first();

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = now();
        $values = [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => $now->copy()->addMinutes(self::OTP_MENIT),
            'last_sent_at' => $now,
            'verified_at' => null,
            'consumed_at' => null,
            'recipient_email' => $recipientEmail,
            'updated_at' => $now,
        ];

        if ($otp) {
            DB::table('otp_codes')->where('id_otp_code', $otp->id_otp_code)->update($values);
            $otpId = (int) $otp->id_otp_code;
        } else {
            $otpId = (int) DB::table('otp_codes')->insertGetId(array_merge($values, [
                'id_user' => $user->getKey(),
                'tujuan' => $tujuan,
                'created_at' => $now,
            ]), 'id_otp_code');
        }

        try {
            Mail::to($recipientEmail)->send(new PasswordResetOtp($code, self::OTP_MENIT));
        } catch (TransportExceptionInterface $exception) {
            DB::table('otp_codes')->where('id_otp_code', $otpId)
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            throw $exception;
        }
    }

    private function emailDeliveryAvailable(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        return ! in_array($transport, ['log', 'array', 'failover', 'roundrobin'], true);
    }

    private function otpRecipient(User $user): string
    {
        $emailOverride = config('mahasiswa.otp_email');
        if (app()->environment('local')
            && (int) $user->id_role === 4
            && filled($emailOverride)) {
            return $emailOverride;
        }

        return $user->email;
    }
}
