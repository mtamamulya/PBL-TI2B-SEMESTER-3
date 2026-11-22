<?php

namespace App\BackendKel1\ImportExcel;

use App\Http\Controllers\Controller;
use App\Repositories\KurikulumStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor mata kuliah dari Excel untuk admin dan kaprodi (satu controller, dua awalan route: /admin dan /kaprodi).
 * Alur: unduh template -> unggah (kurikulum dipilih) -> PRATINJAU (belum menyimpan) -> konfirmasi -> simpan baris valid.
 * Berkas unggahan disimpan sementara di storage/app/private/import-tmp dan diperiksa ulang saat konfirmasi.
 */
class ImportMataKuliahController extends Controller
{
    private const SESI = 'import_mk';

    public function __construct(private ImportMataKuliah $import, private KurikulumStore $kurikulum) {}

    public function form(Request $request)
    {
        $peran = $this->peran($request);

        return view('backendkel1.import.mata-kuliah', [
            'peran' => $peran,
            'layout' => 'layouts.'.$peran,
            'kurikulumList' => $this->kurikulum->options(),
            'kurikulumId' => (string) old('kurikulum', $request->query('kurikulum', '')),
            'preview' => session(self::SESI),
        ]);
    }

    public function template(): StreamedResponse
    {
        $buku = $this->import->template();

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');
        }, 'template-mata-kuliah.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function pratinjau(Request $request): RedirectResponse
    {
        $peran = $this->peran($request);
        $daftar = collect($this->kurikulum->options());

        $request->validate([
            'kurikulum' => ['required', Rule::in($daftar->pluck('id')->all())],
            'berkas' => ['required', 'file', 'max:5120', 'extensions:xlsx'],
        ], [
            'kurikulum.required' => 'Pilih kurikulum tujuan.',
            'kurikulum.in' => 'Kurikulum tidak valid.',
            'berkas.required' => 'Pilih berkas Excel (.xlsx) yang akan diunggah.',
            'berkas.extensions' => 'Berkas harus berformat .xlsx.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $this->bersihkan();   // buang pratinjau lama (bila ada) beserta berkas sementaranya

        $token = Str::random(40);
        $relatif = $request->file('berkas')->storeAs('import-tmp', $token.'.xlsx', 'local');
        $kurikulumId = (string) $request->input('kurikulum');

        $hasil = $this->import->periksa($kurikulumId, Storage::disk('local')->path($relatif));

        if (isset($hasil['error'])) {
            Storage::disk('local')->delete($relatif);

            return redirect()->route($peran.'.kurikulum.mata-kuliah.import', ['kurikulum' => $kurikulumId])
                ->withInput()
                ->withErrors(['berkas' => $hasil['error']]);
        }

        session([self::SESI => [
            'token' => $token,
            'kurikulum' => $kurikulumId,
            'kurikulum_nama' => (string) ($daftar->firstWhere('id', $kurikulumId)['nama'] ?? ''),
            'nama_berkas' => $request->file('berkas')->getClientOriginalName(),
            'hasil' => $hasil,
        ]]);

        return redirect()->route($peran.'.kurikulum.mata-kuliah.import', ['kurikulum' => $kurikulumId]);
    }

    public function simpan(Request $request): RedirectResponse
    {
        $peran = $this->peran($request);
        $sesi = session(self::SESI);
        $relatif = $sesi ? 'import-tmp/'.$sesi['token'].'.xlsx' : null;

        if (! $sesi || ! Storage::disk('local')->exists($relatif)) {
            session()->forget(self::SESI);

            return redirect()->route($peran.'.kurikulum.mata-kuliah.import')
                ->with('galat', 'Sesi pratinjau sudah berakhir. Unggah berkas lagi.');
        }

        // Periksa ulang: data bisa berubah sejak pratinjau (mis. kode sudah dibuat orang lain).
        $hasil = $this->import->periksa($sesi['kurikulum'], Storage::disk('local')->path($relatif));
        if (isset($hasil['error'])) {
            $this->bersihkan();

            return redirect()->route($peran.'.kurikulum.mata-kuliah.import')->with('galat', $hasil['error']);
        }

        $tersimpan = $this->import->simpan($sesi['kurikulum'], $hasil['valid']);
        $this->bersihkan();

        $pesan = "Impor selesai: {$tersimpan} mata kuliah ditambahkan";
        if ($hasil['dilewati']) {
            $pesan .= ', '.count($hasil['dilewati']).' dilewati (sudah ada)';
        }
        if ($hasil['salah']) {
            $pesan .= ', '.count($hasil['salah']).' baris salah tidak diimpor';
        }

        return redirect()->route($peran.'.kurikulum.mata-kuliah', ['kurikulum' => $sesi['kurikulum']])
            ->with('success', $pesan.'.');
    }

    public function batal(Request $request): RedirectResponse
    {
        $this->bersihkan();

        return redirect()->route($this->peran($request).'.kurikulum.mata-kuliah.import');
    }

    /** 'admin' atau 'kaprodi', dari awalan URL. Penjaga akses (AdminOnly/KaprodiOnly) dipasang di routes.php. */
    private function peran(Request $request): string
    {
        return $request->segment(1) === 'kaprodi' ? 'kaprodi' : 'admin';
    }

    private function bersihkan(): void
    {
        $sesi = session(self::SESI);
        if ($sesi) {
            Storage::disk('local')->delete('import-tmp/'.$sesi['token'].'.xlsx');
        }
        session()->forget(self::SESI);
    }
}
