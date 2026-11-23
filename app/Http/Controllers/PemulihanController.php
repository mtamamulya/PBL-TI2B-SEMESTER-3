<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PemulihanController extends Controller
{
    /** Setelah OTP benar, waktu untuk mengisi dan menyimpan sandi baru (menit). */
    private const SANDI_BARU_MENIT = 7;

    private const OTP_JEDA_KIRIM_DETIK = 60;

    public function show(Request $request)
    {
        if ($request->boolean('mulai')) {
            session()->forget('pemulihan');
        }

        $step = (int) session('pemulihan.step', 1);

        // Langkah 3 hanya boleh tampil selama batas waktu pembuatan sandi baru belum habis.
        if ($step === 3 && ! $this->sesiSandiBaruMasihBerlaku()) {
            session()->forget('pemulihan');

            return redirect()->route('password.request')
                ->withErrors(['email' => 'Waktu membuat kata sandi baru sudah habis. Minta kode OTP baru.']);
        }

        $data = [
            'step' => $step,
            'email' => session('pemulihan.email'),
            'batasMenit' => self::SANDI_BARU_MENIT,
        ];

        $view = view('pemulihan', $data);

        if ($step === 4) {
            session()->forget('pemulihan');
        }

        return $view;
    }

    public function kirimOtp(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:150']], [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        $email = Str::lower(trim($data['email']));

        try {
            ApiClient::publik()->post('/lupa-sandi/kirim-otp', ['email' => $email]);
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 422) {
                throw $e;
            }

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => $e->getMessage()]);
        }

        session([
            'pemulihan' => [
                'step' => 2,
                'email' => $email,
                'terakhir_kirim' => now()->timestamp,
            ],
        ]);

        return redirect()->route('password.request')
            ->with('status', 'Jika email terdaftar, kode OTP telah dikirim. Periksa kotak masuk atau folder spam.');
    }

    public function kirimUlang(): RedirectResponse
    {
        $email = session('pemulihan.email');
        if (! $email) {
            return redirect()->route('password.request');
        }

        $sisa = self::OTP_JEDA_KIRIM_DETIK - (now()->timestamp - (int) session('pemulihan.terakhir_kirim', 0));
        if ($sisa > 0) {
            return redirect()->route('password.request')
                ->withErrors(['otp' => "Tunggu {$sisa} detik sebelum meminta kode baru."]);
        }

        try {
            ApiClient::publik()->post('/lupa-sandi/kirim-otp', ['email' => $email]);
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 422) {
                throw $e;
            }

            return redirect()->route('password.request')
                ->withErrors(['otp' => $e->getMessage()]);
        }

        session(['pemulihan.terakhir_kirim' => now()->timestamp]);

        return redirect()->route('password.request')
            ->with('status', 'Jika email terdaftar, kode baru telah dikirim. Periksa kotak masuk atau folder spam.');
    }

    public function ubahEmail(): RedirectResponse
    {
        session()->forget('pemulihan');

        return redirect()->route('password.request');
    }

    public function verifikasi(Request $request): RedirectResponse
    {
        abort_unless(session('pemulihan.step') === 2, 404);

        $data = $request->validate(['otp' => ['required', 'digits:6']], [
            'otp.required' => 'Masukkan 6 digit kode OTP.',
            'otp.digits' => 'Kode OTP harus 6 digit angka.',
        ]);

        try {
            $hasil = ApiClient::publik()->post('/lupa-sandi/verifikasi', [
                'email' => session('pemulihan.email'),
                'otp' => $data['otp'],
            ]);
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 422) {
                throw $e;
            }

            return redirect()->route('password.request')
                ->withErrors(['otp' => $e->getMessage()]);
        }

        $batasMenit = (int) ($hasil['data']['batas_menit'] ?? self::SANDI_BARU_MENIT);

        session([
            'pemulihan.step' => 3,
            'pemulihan.otp_id' => $hasil['data']['otp_id'] ?? null,
            'pemulihan.berlaku_sampai' => now()->addMinutes($batasMenit)->timestamp,
        ]);

        return redirect()->route('password.request');
    }

    public function simpan(Request $request): RedirectResponse
    {
        abort_unless(session('pemulihan.step') === 3, 404);

        $data = $request->validate([
            'password' => ['required', 'min:8', 'confirmed'],
        ], [
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ]);

        try {
            ApiClient::publik()->post('/lupa-sandi/simpan', [
                'email' => session('pemulihan.email'),
                'otp_id' => session('pemulihan.otp_id'),
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ]);
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 422) {
                throw $e;
            }

            return redirect()->route('password.request')
                ->withErrors(['password' => $e->getMessage()]);
        }

        session(['pemulihan.step' => 4]);

        return redirect()->route('password.request');
    }

    private function sesiSandiBaruMasihBerlaku(): bool
    {
        $id = session('pemulihan.otp_id');
        $berlakuSampai = (int) session('pemulihan.berlaku_sampai', 0);

        return ! empty($id) && now()->timestamp <= $berlakuSampai;
    }
}
