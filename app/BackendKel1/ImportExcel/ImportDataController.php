<?php

namespace App\BackendKel1\ImportExcel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor dosen dan mahasiswa dari Excel (khusus admin). Satu controller untuk dua jenis, dipilih lewat {jenis} di URL.
 * Alur: unduh template -> unggah -> PRATINJAU (belum menyimpan) -> konfirmasi -> simpan baris valid.
 * Berkas unggahan disimpan sementara di storage/app/private/import-tmp dan diperiksa ulang saat konfirmasi.
 */
class ImportDataController extends Controller
{
    /** @var array<string, array{kelas: class-string<Impor>, judul: string, satuan: string, berkas: string, daftar: string, sandi: string}> */
    private const JENIS = [
        'dosen' => [
            'kelas' => ImportDosen::class,
            'judul' => 'Dosen',
            'satuan' => 'dosen',
            'berkas' => 'template-dosen.xlsx',
            'daftar' => 'admin.dosen',
            'sandi' => 'NIP',
        ],
        'mahasiswa' => [
            'kelas' => ImportMahasiswa::class,
            'judul' => 'Mahasiswa',
            'satuan' => 'mahasiswa',
            'berkas' => 'template-mahasiswa.xlsx',
            'daftar' => 'admin.mahasiswa',
            'sandi' => 'NIM',
        ],
    ];

    public function form(string $jenis)
    {
        $c = $this->konfigurasi($jenis);

        return view('backendkel1.import.data', [
            'jenis' => $jenis,
            'c' => $c,
            'kolom' => app($c['kelas'])->kolomPratinjau(),
            'preview' => session($this->sesi($jenis)),
        ]);
    }

    public function template(string $jenis): StreamedResponse
    {
        $c = $this->konfigurasi($jenis);
        $buku = app($c['kelas'])->template();

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');
        }, $c['berkas'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pratinjau(Request $request, string $jenis): RedirectResponse
    {
        $c = $this->konfigurasi($jenis);

        $request->validate([
            'berkas' => ['required', 'file', 'max:5120', 'extensions:xlsx'],
        ], [
            'berkas.required' => 'Pilih berkas Excel (.xlsx) yang akan diunggah.',
            'berkas.extensions' => 'Berkas harus berformat .xlsx.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $this->bersihkan($jenis);

        $token = Str::random(40);
        $relatif = $request->file('berkas')->storeAs('import-tmp', $token.'.xlsx', 'local');
        $hasil = app($c['kelas'])->periksa(Storage::disk('local')->path($relatif));

        if (isset($hasil['error'])) {
            Storage::disk('local')->delete($relatif);

            return redirect()->route('admin.impor.form', ['jenis' => $jenis])->withErrors(['berkas' => $hasil['error']]);
        }

        session([$this->sesi($jenis) => [
            'token' => $token,
            'nama_berkas' => $request->file('berkas')->getClientOriginalName(),
            'hasil' => $hasil,
        ]]);

        return redirect()->route('admin.impor.form', ['jenis' => $jenis]);
    }

    public function simpan(string $jenis): RedirectResponse
    {
        $c = $this->konfigurasi($jenis);
        $sesi = session($this->sesi($jenis));
        $relatif = $sesi ? 'import-tmp/'.$sesi['token'].'.xlsx' : null;

        if (! $sesi || ! Storage::disk('local')->exists($relatif)) {
            session()->forget($this->sesi($jenis));

            return redirect()->route('admin.impor.form', ['jenis' => $jenis])
                ->with('galat', 'Sesi pratinjau sudah berakhir. Unggah berkas lagi.');
        }

        // Periksa ulang: data bisa berubah sejak pratinjau (mis. NIP/NIM sudah didaftarkan orang lain).
        $impor = app($c['kelas']);
        $hasil = $impor->periksa(Storage::disk('local')->path($relatif));
        if (isset($hasil['error'])) {
            $this->bersihkan($jenis);

            return redirect()->route('admin.impor.form', ['jenis' => $jenis])->with('galat', $hasil['error']);
        }

        $simpan = $impor->simpan($hasil['valid']);
        $this->bersihkan($jenis);

        $lewat = count($hasil['dilewati']) + $simpan['dilewati'];
        $pesan = "Impor selesai: {$simpan['tersimpan']} {$c['satuan']} ditambahkan";
        if ($lewat) {
            $pesan .= ", {$lewat} dilewati (sudah terdaftar)";
        }
        if ($hasil['salah']) {
            $pesan .= ', '.count($hasil['salah']).' baris salah tidak diimpor';
        }
        if ($simpan['gagal']) {
            $pesan .= ', '.count($simpan['gagal']).' gagal disimpan';
        }

        return redirect()->route($c['daftar'])->with('success', $pesan.'. Sandi awal akun = '.$c['sandi'].'.');
    }

    public function batal(string $jenis): RedirectResponse
    {
        $this->konfigurasi($jenis);
        $this->bersihkan($jenis);

        return redirect()->route('admin.impor.form', ['jenis' => $jenis]);
    }

    private function konfigurasi(string $jenis): array
    {
        return self::JENIS[$jenis] ?? abort(404);
    }

    private function sesi(string $jenis): string
    {
        return 'import_'.$jenis;
    }

    private function bersihkan(string $jenis): void
    {
        $sesi = session($this->sesi($jenis));
        if ($sesi) {
            Storage::disk('local')->delete('import-tmp/'.$sesi['token'].'.xlsx');
        }
        session()->forget($this->sesi($jenis));
    }
}
