<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TahunAkademikController extends Controller
{
    private function apiUrl(string $path): string
    {
        $base = rtrim((string) config('services.backend_kel1.url', env('BACKEND_KEL1_URL', 'http://127.0.0.1:8001/api/v1')), '/');

        return "{$base}{$path}";
    }

    public function index(Request $request)
    {
        $response = Http::acceptJson()->get($this->apiUrl('/tahun-akademik'));
        $list = $response->json('data') ?? [];
        $aktif = collect($list)->first(function ($item) {
            return in_array(strtolower((string) ($item['status'] ?? '')), ['1', 'true', 'aktif'], true);
        });

        return view('admin.tahun-akademik', [
            'tahunAkademikList' => $list,
            'tahunAkademikAktif' => $aktif,
            'isAdmin' => $this->isAdmin($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isAdmin($request), 403, 'Hanya Admin yang dapat menambahkan tahun akademik.');

        $validated = $request->validate([
            'semester' => ['required', 'in:Ganjil,Genap'],
            'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
        ]);

        $token = (string) ($request->session()->get('api_token') ?? $request->session()->get('token') ?? '');

        $response = Http::acceptJson()
            ->withToken($token)
            ->post($this->apiUrl('/tahun-akademik'), $validated);

        if ($response->status() === 422) {
            return back()
                ->withInput()
                ->withErrors($response->json('errors') ?? ['tahun_ajaran' => [$response->json('message', 'Data tidak valid.')]]);
        }

        if (! $response->successful()) {
            return back()
                ->withInput()
                ->withErrors(['tahun_ajaran' => [$response->json('message', 'Gagal menyimpan tahun akademik ke API.')]]);
        }

        return redirect()
            ->route('admin.kurikulum.tahun-akademik')
            ->with('status', $response->json('message') ?? 'Tahun akademik baru berhasil ditambahkan dan diaktifkan.');
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        $roleName = strtolower((string) ($user->role->nama_role ?? $user->role ?? session('role', '')));

        return $roleName === 'admin' || (int) ($user->id_role ?? 0) === 1;
    }
}