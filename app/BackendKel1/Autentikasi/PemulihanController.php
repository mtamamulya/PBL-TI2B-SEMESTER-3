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
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PemulihanController extends Controller
{
    private const TUJUAN_LUPA_SANDI = 'lupa_sandi';

    private const TUJUAN_PROFIL = 'ganti_sandi';

    private const OTP_MENIT = 5;

    private const SANDI_BARU_MENIT = 7;

    private const OTP_MAKS_PERCOBAAN = 3;

    private const OTP_JEDA_KIRIM_DETIK = 60;

    // =========================================================================
    // ALUR 1: LUPA SANDI (PUBLIK / BELUM LOGIN)
    // =========================================================================

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
            // Masih dalam jeda: tidak mengirim ulang, tetapi jawabannya SAMA dengan permintaan biasa
            // agar penyerang tidak bisa membedakan email terdaftar dari yang tidak. (Web punya hitungan jeda sendiri.)
            if ($this->sisaJedaKirim($user, self::TUJUAN_LUPA_SANDI) > 0) {
                return ApiResponse::ok(null, 'Jika email terdaftar, kode OTP telah dikirim. Periksa kotak masuk atau folder spam.');
            }

            try {
                $this->sendOtp($user, self::TUJUAN_LUPA_SANDI);
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

    // =========================================================================
    // ALUR 2A: GANTI SANDI PROFIL (VERIFIKASI SANDI LAMA)
    // =========================================================================

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

    // =========================================================================
    // ALUR 2B: GANTI SANDI PROFIL (DENGAN OTP EMAIL)
    // =========================================================================

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
            $this->sendOtp($user, self::TUJUAN_PROFIL);
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

    // =========================================================================
    // HELPER INTERNAL OTP & TOKEN
    // =========================================================================

    private function prosesVerifikasiOtp(User $user, string $kodeInput, string $tujuan): JsonResponse
    {
        $otp = DB::table('otp_codes')
            ->where('id_user', $user->getKey())
            ->where('tujuan', $tujuan)
            ->whereNull('consumed_at')
            ->whereNull('verified_at')
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
        // Sengaja TANPA filter consumed_at: kode yang hangus (3x salah / sudah dipakai) tidak boleh menghapus jeda.
        $otp = DB::table('otp_codes')
            ->where('id_user', $user->getKey())
            ->where('tujuan', $tujuan)
            ->latest('id_otp_code')
            ->first();

        if (! $otp || ! $otp->last_sent_at) {
            return 0;
        }

        $batas = Carbon::parse($otp->last_sent_at)->addSeconds(self::OTP_JEDA_KIRIM_DETIK);

        return $batas->isFuture() ? (int) now()->diffInSeconds($batas) : 0;
    }

    private function sendOtp(User $user, string $tujuan): void
    {
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
            Mail::to($user->email)->send(new PasswordResetOtp($code, self::OTP_MENIT));
        } catch (TransportExceptionInterface $exception) {
            DB::table('otp_codes')->where('id_otp_code', $otpId)
                ->update(['consumed_at' => now(), 'last_sent_at' => null, 'updated_at' => now()]);

            throw $exception;
        }
    }

    private function emailDeliveryAvailable(): bool
    {
        if (app()->environment(['testing', 'local'])) {
            return true;
        }

        $mailer = config('mail.default');
        $transport = config("mail.mailers.{$mailer}.transport");

        return ! in_array($transport, ['log', 'array', 'failover', 'roundrobin'], true);
    }
}