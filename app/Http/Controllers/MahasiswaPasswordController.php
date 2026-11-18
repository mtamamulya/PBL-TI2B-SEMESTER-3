<?php

namespace App\Http\Controllers;

use App\Mail\PasswordResetOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MahasiswaPasswordController extends Controller
{
    private const OTP_MENIT = 5;

    private const OTP_MAKS_PERCOBAAN = 3;

    private const OTP_JEDA_KIRIM_DETIK = 60;

    public function kirimOtp(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols(), 'confirmed'],
        ], [
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        $user = $request->user();
        abort_unless((int) $user->id_role === 4, 403);

        if (! $this->emailDeliveryAvailable()) {
            return response()->json([
                'message' => 'Layanan email belum dikonfigurasi. Hubungi administrator.',
            ], 503);
        }

        $otp = DB::table('otp_codes')
            ->where('id_user', $user->getKey())
            ->whereNull('consumed_at')
            ->latest('id_otp_code')
            ->first();

        if ($otp && Carbon::parse($otp->last_sent_at)
            ->addSeconds(self::OTP_JEDA_KIRIM_DETIK)->isFuture()) {
            $otpId = (int) $otp->id_otp_code;
            $resendAt = Carbon::parse($otp->last_sent_at)->addSeconds(self::OTP_JEDA_KIRIM_DETIK);

            $this->rememberOtp($request, $user->getKey(), $otpId);

            return response()->json([
                'message' => 'Kode OTP sebelumnya masih berlaku. Periksa email Anda.',
                'expires_in_seconds' => max(1, Carbon::parse($otp->expires_at)->getTimestamp() - now()->getTimestamp()),
                'resend_after_seconds' => max(1, $resendAt->getTimestamp() - now()->getTimestamp()),
            ]);
        }

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
                'created_at' => $now,
            ]), 'id_otp_code');
        }

        try {
            Mail::to(config('mahasiswa.otp_email'))->send(new PasswordResetOtp($code, self::OTP_MENIT));
        } catch (TransportExceptionInterface $exception) {
            DB::table('otp_codes')->where('id_otp_code', $otpId)
                ->update(['consumed_at' => now(), 'updated_at' => now()]);
            report($exception);

            return response()->json([
                'message' => 'Kode OTP gagal dikirim. Coba lagi beberapa saat.',
            ], 503);
        }

        $this->rememberOtp($request, $user->getKey(), $otpId);

        return response()->json([
            'message' => 'Kode OTP telah dikirim ke email terdaftar.',
            'expires_in_seconds' => self::OTP_MENIT * 60,
            'resend_after_seconds' => self::OTP_JEDA_KIRIM_DETIK,
        ]);
    }

    public function verifikasi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()->symbols(), 'confirmed'],
        ], [
            'otp.required' => 'Masukkan 6 digit kode OTP.',
            'otp.digits' => 'Kode OTP harus 6 digit angka.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        $user = $request->user();
        abort_unless((int) $user->id_role === 4, 403);
        $pending = $request->session()->get('mahasiswa_password_change', []);
        $otpId = (int) ($pending['otp_id'] ?? 0);

        if (! $otpId || (int) ($pending['user_id'] ?? 0) !== (int) $user->getKey()) {
            return response()->json([
                'message' => 'Sesi verifikasi tidak valid. Kirim ulang kode OTP.',
            ], 422);
        }

        $result = DB::transaction(function () use ($data, $otpId, $user) {
            $otp = DB::table('otp_codes')
                ->where('id_otp_code', $otpId)
                ->where('id_user', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $otp || $otp->consumed_at !== null) {
                return ['message' => 'Kode OTP tidak valid atau sudah digunakan.', 'clear' => true];
            }

            if (Carbon::parse($otp->expires_at)->isPast()) {
                $this->consumeOtp($otpId);

                return ['message' => 'Kode OTP sudah kedaluwarsa. Kirim ulang kode.', 'clear' => true];
            }

            if ((int) $otp->attempts >= self::OTP_MAKS_PERCOBAAN) {
                $this->consumeOtp($otpId);

                return ['message' => 'Batas percobaan tercapai. Kirim ulang kode.', 'clear' => true];
            }

            if (! Hash::check((string) $data['otp'], $otp->code_hash)) {
                DB::table('otp_codes')->where('id_otp_code', $otpId)
                    ->increment('attempts', 1, ['updated_at' => now()]);
                $attempts = (int) DB::table('otp_codes')->where('id_otp_code', $otpId)->value('attempts');

                if ($attempts >= self::OTP_MAKS_PERCOBAAN) {
                    $this->consumeOtp($otpId);
                }

                return [
                    'message' => $attempts >= self::OTP_MAKS_PERCOBAAN
                        ? 'Batas percobaan tercapai. Kirim ulang kode.'
                        : 'Kode OTP tidak valid.',
                    'clear' => $attempts >= self::OTP_MAKS_PERCOBAAN,
                ];
            }

            $consumed = DB::table('otp_codes')->where('id_otp_code', $otpId)
                ->whereNull('consumed_at')
                ->update(['verified_at' => now(), 'consumed_at' => now(), 'updated_at' => now()]);

            if ($consumed !== 1) {
                return ['message' => 'Kode OTP sudah digunakan. Kirim ulang kode.', 'clear' => true];
            }

            $updated = $user->newQuery()->whereKey($user->getKey())->update([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
                'updated_at' => now(),
            ]);

            if ($updated !== 1) {
                throw new \RuntimeException('Password mahasiswa tidak berhasil diperbarui.');
            }

            return ['success' => true];
        });

        if (! ($result['success'] ?? false)) {
            if ($result['clear'] ?? false) {
                $request->session()->forget('mahasiswa_password_change');
            }

            return response()->json(['message' => $result['message']], 422);
        }

        $request->session()->forget('mahasiswa_password_change');

        return response()->json(['message' => 'Kata sandi berhasil diperbarui.']);
    }

    private function rememberOtp(Request $request, int|string $userId, int $otpId): void
    {
        $request->session()->put('mahasiswa_password_change', [
            'user_id' => $userId,
            'otp_id' => $otpId,
        ]);
    }

    private function consumeOtp(int $otpId): void
    {
        DB::table('otp_codes')->where('id_otp_code', $otpId)
            ->update(['consumed_at' => now(), 'updated_at' => now()]);
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
}
