<?php

namespace App\BackendKel1\ProfilProdi;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Support\ProfilProdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * /api/v1/prodi/profil — profil prodi tunggal (baris pertama tabel `prodi`).
 * Bentuk JSON = kontrak App\Support\ProfilProdi (docs/kontrak-profil-prodi.md).
 */
class ProfilProdiController extends Controller
{
    private const KOLOM_JSON = ['misi', 'tujuan', 'profil_lulusan'];

    private const KOLOM_LOGO = ['utama' => 'logo', 'footer' => 'logo_footer'];

    public function show(): JsonResponse
    {
        return ApiResponse::ok(self::bentuk($this->baris()));
    }

    public function update(Request $request): JsonResponse
    {
        $baris = $this->baris();

        $input = ProfilProdi::bersihkan($request->all());
        $aturan = ProfilProdi::aturan();
        $aturan['kode_prodi'][] = Rule::unique('prodi', 'kode_prodi')->ignore($baris?->id_prodi, 'id_prodi');

        $v = Validator::make($input, $aturan, ProfilProdi::pesan());
        $v->after(function ($v) use ($baris, $input) {
            // Belum ada baris prodi: identitas wajib ikut dikirim agar baris bisa dibuat.
            if (! $baris) {
                foreach (['kode_prodi', 'nama_prodi', 'jenjang'] as $k) {
                    if (empty($input[$k])) {
                        $v->errors()->add($k, "Data prodi belum ada, {$k} wajib diisi.");
                    }
                }
            }
        });
        $data = $v->validate();

        $simpan = [];
        foreach ($data as $kunci => $nilai) {
            $simpan[$kunci] = in_array($kunci, self::KOLOM_JSON, true)
                ? json_encode($nilai, JSON_UNESCAPED_UNICODE)
                : $nilai;
        }
        $simpan['updated_at'] = now();

        if ($baris) {
            DB::table('prodi')->where('id_prodi', $baris->id_prodi)->update($simpan);
        } else {
            $simpan['created_at'] = now();
            DB::table('prodi')->insert($simpan);
        }

        return ApiResponse::ok(self::bentuk($this->baris()), 'Profil prodi berhasil disimpan.');
    }

    public function unggahLogo(Request $request): JsonResponse
    {
        $request->validate([
            'jenis' => ['required', Rule::in(array_keys(self::KOLOM_LOGO))],
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $baris = $this->baris();
        if (! $baris) {
            return ApiResponse::gagal('Data prodi belum ada. Simpan identitas prodi terlebih dahulu.');
        }

        $kolom = self::KOLOM_LOGO[$request->input('jenis')];
        $path = $request->file('file')->store('prodi', 'public');

        $lama = $baris->{$kolom} ?? null;
        DB::table('prodi')->where('id_prodi', $baris->id_prodi)->update([$kolom => $path, 'updated_at' => now()]);
        if ($lama) {
            Storage::disk('public')->delete($lama);
        }

        return ApiResponse::ok(self::bentuk($this->baris()), 'Logo berhasil diunggah.', 201);
    }

    public function hapusLogo(string $jenis): JsonResponse
    {
        $baris = $this->baris();
        if (! $baris) {
            return ApiResponse::gagal('Data prodi belum ada.');
        }

        $kolom = self::KOLOM_LOGO[$jenis];
        $lama = $baris->{$kolom} ?? null;
        DB::table('prodi')->where('id_prodi', $baris->id_prodi)->update([$kolom => null, 'updated_at' => now()]);
        if ($lama) {
            Storage::disk('public')->delete($lama);
        }

        return ApiResponse::ok(self::bentuk($this->baris()), 'Logo berhasil dihapus.');
    }

    private function baris(): ?object
    {
        return DB::table('prodi')->orderBy('id_prodi')->first();
    }

    /** Bentuk sesuai kontrak. Tanpa baris -> semua kosong (bukan data contoh). */
    public static function bentuk(?object $b): array
    {
        $hasil = array_fill_keys(ProfilProdi::KUNCI, null);
        $hasil['misi'] = $hasil['tujuan'] = $hasil['profil_lulusan'] = [];

        if (! $b) {
            return $hasil;
        }

        foreach (ProfilProdi::DAPAT_DIUBAH as $k) {
            $nilai = $b->{$k} ?? null;
            $hasil[$k] = in_array($k, self::KOLOM_JSON, true)
                ? (is_string($nilai) ? (json_decode($nilai, true) ?: []) : (array) ($nilai ?? []))
                : $nilai;
        }

        $hasil['logo_url'] = ! empty($b->logo) ? Storage::disk('public')->url($b->logo) : null;
        $hasil['logo_footer_url'] = ! empty($b->logo_footer) ? Storage::disk('public')->url($b->logo_footer) : null;

        return $hasil;
    }
}
