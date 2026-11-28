<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TahunAkademikController extends Controller
{
    public function index(Request $request)
    {
        $hasil = ApiClient::publik()->get('/tahun-akademik');
        $list = $hasil['data'] ?? [];

        $aktif = collect($list)->first(function ($item) {
            return in_array(strtolower((string) ($item['status'] ?? '')), ['1', 'true', 'aktif'], true);
        });

        $periodeBerikutnya = $this->hitungPeriodeBerikutnya($list, $aktif);
        $isAdmin = request()->routeIs('admin.*');

        return view($isAdmin ? 'admin.tahun-akademik' : 'kaprodi.tahun-akademik', [
            'tahunAkademikList' => $list,
            'tahunAkademikAktif' => $aktif,
            'defaultSemester' => $periodeBerikutnya['semester'],
            'defaultTahunAjaran' => $periodeBerikutnya['tahun_ajaran'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isAdmin($request), 403, 'Hanya Admin yang dapat menambahkan tahun akademik.');

        $validated = $request->validate([
            'semester' => ['required', 'in:Ganjil,Genap'],
            'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
        ]);

        try {
            $hasil = ApiClient::pengguna()->post('/tahun-akademik', $validated);
        } catch (ApiException $e) {
            if ($e->status === 422) {
                return back()
                    ->withInput()
                    ->withErrors($e->errors ?: ['tahun_ajaran' => [$e->getMessage()]]);
            }

            return back()
                ->withInput()
                ->withErrors(['tahun_ajaran' => [$e->getMessage()]]);
        }

        return redirect()
            ->route('admin.kurikulum.tahun-akademik')
            ->with('status', $hasil['message'] ?? 'Tahun akademik baru berhasil ditambahkan dan diaktifkan.');
    }

    private function hitungPeriodeBerikutnya(array $list, ?array $aktif): array
    {
        $kandidat = collect($list);
        if ($aktif) {
            $kandidat = $kandidat->prepend($aktif);
        }

        $terbaru = null;
        $skorTertinggi = -1;

        foreach ($kandidat as $item) {
            $label = (string) ($item['tahun_akademik'] ?? '');
            if (preg_match('/^(Ganjil|Genap)\s+(\d{4})\/(\d{4})$/i', trim($label), $m)) {
                $sem = ucfirst(strtolower($m[1]));
                $tahunAwal = (int) $m[2];
                $tahunAkhir = (int) $m[3];
                $skor = ($tahunAwal * 2) + ($sem === 'Genap' ? 1 : 0);

                if ($skor > $skorTertinggi) {
                    $skorTertinggi = $skor;
                    $terbaru = [
                        'semester' => $sem,
                        'tahun_awal' => $tahunAwal,
                        'tahun_akhir' => $tahunAkhir,
                    ];
                }
            }
        }

        if (! $terbaru) {
            $tahun = (int) date('Y');

            return [
                'semester' => 'Ganjil',
                'tahun_ajaran' => "{$tahun}/".($tahun + 1),
            ];
        }

        if ($terbaru['semester'] === 'Ganjil') {
            return [
                'semester' => 'Genap',
                'tahun_ajaran' => "{$terbaru['tahun_awal']}/{$terbaru['tahun_akhir']}",
            ];
        }

        return [
            'semester' => 'Ganjil',
            'tahun_ajaran' => ($terbaru['tahun_awal'] + 1).'/'.($terbaru['tahun_akhir'] + 1),
        ];
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        $roleName = strtolower((string) ($user->role->nama_role ?? $user->role ?? session('role', '')));

        return $roleName === 'admin' || (int) ($user->id_role ?? 0) === 1;
    }
}
