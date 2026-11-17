<?php

namespace App\Http\Controllers;

use App\Models\Prestasi;
use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Support\Carbon;

class KaprodiPrestasiController extends Controller
{
    public function index()
    {
        return view('kaprodi.prestasi', ['rows' => $this->daftar()]);
    }

    public function show($id)
    {
        try {
            $hasil = ApiClient::pengguna()->get("/prestasi/{$id}");
        } catch (ApiException $e) {
            if ($e->getCode() === 404) {
                abort(404);
            }

            throw $e;
        }

        $item = $hasil['data'] ?? null;

        if (! is_array($item)) {
            abort(404);
        }

        $prestasi = $this->bentukBaris($item);
        $modelAsli = $this->bentukObjekDetail($item);

        return view('kaprodi.prestasi-detail', compact('prestasi', 'modelAsli'));
    }

    private function daftar(): array
    {
        $hasil = ApiClient::pengguna()->get('/prestasi', ['per_page' => 100]);

        return collect($hasil['data'] ?? [])
            ->map(fn (array $item) => $this->bentukBaris($item))
            ->all();
    }

    /** @param array<string, mixed> $item */
    private function bentukBaris(array $item): array
    {
        $keg = is_array($item['kegiatan'] ?? null) ? $item['kegiatan'] : null;
        $usr = is_array($keg['user'] ?? null) ? $keg['user'] : null;

        $namaPengunggah = $usr ? ($usr['nama'] ?? 'User Tidak Diketahui') : 'Sistem';

        $tipeUser = 'Mahasiswa';
        if ($usr) {
            if (isset($usr['role']) && strtolower((string) $usr['role']) === 'dosen') {
                $tipeUser = 'Dosen';
            } elseif (! empty($usr['is_dosen'])) {
                $tipeUser = 'Dosen';
            }
        }

        $tanggalMulai = ! empty($keg['tanggal_mulai'])
            ? Carbon::parse($keg['tanggal_mulai'])
            : now();

        $bulanAngka = (int) $tanggalMulai->format('n');
        $namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $stringTanggal = $tanggalMulai->format('j').' '.$namaBulan[$bulanAngka].' '.$tanggalMulai->format('Y');
        $semester = ($bulanAngka >= 8 || $bulanAngka <= 1) ? 'Ganjil' : 'Genap';

        return [
            'id' => $item['id_prestasi'] ?? null,
            'nama' => $keg['nama_kegiatan'] ?? 'Tidak Ada Judul',
            'kategori' => $item['jenis_prestasi'] ?? 'Lomba',
            'pengunggah' => $namaPengunggah,
            'tipe' => $tipeUser,
            'anggota' => [$namaPengunggah],
            'tanggal' => $stringTanggal,
            'raw_date' => $tanggalMulai->toDateString(),
            'semester' => $semester,
        ];
    }

    /**
     * Membentuk objek kompatibel Eloquent dari respons API agar view kaprodi.prestasi-detail tetap berjalan tanpa query DB langsung.
     *
     * @param  array<string, mixed>  $item
     */
    private function bentukObjekDetail(array $item): Prestasi
    {
        $model = new Prestasi;
        $model->forceFill([
            'id_prestasi' => $item['id_prestasi'] ?? null,
            'id_kegiatan' => $item['id_kegiatan'] ?? null,
            'id_prodi' => $item['id_prodi'] ?? null,
            'jenis_prestasi' => $item['jenis_prestasi'] ?? null,
            'bidang_lomba' => $item['bidang_lomba'] ?? null,
            'capaian' => $item['capaian'] ?? null,
            'catatan' => $item['catatan'] ?? null,
        ]);

        if (is_array($item['kegiatan'] ?? null)) {
            $kegData = $item['kegiatan'];
            $kegObj = (object) $kegData;
            if (! empty($kegData['tanggal_mulai'])) {
                $kegObj->tanggal_mulai = Carbon::parse($kegData['tanggal_mulai']);
            }
            if (! empty($kegData['tanggal_selesai'])) {
                $kegObj->tanggal_selesai = Carbon::parse($kegData['tanggal_selesai']);
            }
            $model->setRelation('kegiatan', $kegObj);
        } else {
            $model->setRelation('kegiatan', null);
        }

        $buktiList = collect($item['bukti_prestasi'] ?? [])->map(function (array $b) {
            $bObj = (object) array_merge([
                'nama_file' => $b['nama_file'] ?? $b['file_path'] ?? null,
                'link' => $b['link'] ?? null,
            ], $b);

            if (is_array($b['jenis_dokumen'] ?? null)) {
                $jd = $b['jenis_dokumen'];
                $bObj->jenisDokumen = (object) array_merge([
                    'nama_jenis' => $jd['nama_jenis'] ?? $jd['nama_jenis_dokumen'] ?? null,
                ], $jd);
            } else {
                $bObj->jenisDokumen = null;
            }

            return $bObj;
        });
        $model->setRelation('buktiPrestasi', $buktiList);

        return $model;
    }
}
