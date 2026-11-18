<?php

namespace App\BackendKel3\Penelitian;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DosenPenelitianController extends Controller
{
    public function index(Request $request)
    {
        $dosen = $request->user();

        $penelitian = DB::table('kegiatan_dosen')
            ->join('kegiatan', 'kegiatan_dosen.id_kegiatan', '=', 'kegiatan.id_kegiatan')
            ->leftJoin('skim', 'kegiatan_dosen.id_skim', '=', 'skim.id_skim')
            ->leftJoin('kelompok_bidang', 'kegiatan_dosen.id_kelompok_bidang', '=', 'kelompok_bidang.id_kelompok_bidang')
            ->when($dosen, function ($query) use ($dosen) {
                return $query->where('kegiatan.id_dosen', $dosen->id_dosen ?? $dosen->id);
            })
            ->select(
                'kegiatan.*',
                'kegiatan_dosen.*',
                'skim.nama_skim',
                'kelompok_bidang.nama_kelompok_bidang'
            )
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar data penelitian berhasil dimuat.',
            'data' => $penelitian,
        ]);
    }

    public function store(Request $request)
    {
        // Validasi sesuai inputan form create.blade.php & _medan-kategori.php
        $request->validate([
            'nama_kegiatan' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:150',
            'tahun_penyelenggaraan' => 'required|digits:4',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'detail.tahun_pelaksanaan_ke' => 'required|integer|min:1',
            'detail.lama_kegiatan' => 'required|integer|min:1',
        ]);

        $detail = $request->input('detail', []);
        $bersihkanRupiah = fn ($val) => $val ? (float) str_replace('.', '', $val) : 0;

        try {
            $idKegiatan = DB::transaction(function () use ($request, $detail, $bersihkanRupiah) {
                // Cari ID skim & kelompok bidang dari teks dropdown jika ada di master tabel
                $skim = isset($detail['skim'])
                    ? DB::table('skim')->where('nama_skim', $detail['skim'])->first()
                    : null;

                $bidang = isset($detail['kelompok_bidang'])
                    ? DB::table('kelompok_bidang')->where('nama_kelompok_bidang', $detail['kelompok_bidang'])->first()
                    : null;

                $kategoriRecord = DB::table('kegiatan_kategori') // atau tabel kategori yang dipakai kelompok kalian, misal 'kategori'
                    ->where('nama_kategori', 'LIKE', '%Penelitian%')
                    ->first();

                // 1. Simpan ke tabel induk `kegiatan` (Tanpa id_dosen karena kolom tidak ada di migrasi kegiatan)
                $idKeg = DB::table('kegiatan')->insertGetId([
                    'id_kategori' => 1, // Ganti dengan ID kategori Penelitian di database kalian jika tahu angkanya
                    'nama_kegiatan' => $request->nama_kegiatan,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 2. Simpan ke tabel `kegiatan_dosen`
                DB::table('kegiatan_dosen')->insert([
                    'id_kegiatan' => $idKeg,
                    'id_skim' => $skim?->id_skim,
                    'id_kelompok_bidang' => $bidang?->id_kelompok_bidang,
                    'afiliasi' => $request->penyelenggara,
                    'tahun_anggaran' => $request->tahun_penyelenggaraan,
                    'tahun_pelaksanaan_ke' => $detail['tahun_pelaksanaan_ke'] ?? 1,
                    'lama_kegiatan_tahun' => $detail['lama_kegiatan'] ?? 1,
                    'dana_dikti' => $bersihkanRupiah($detail['dana_dikti'] ?? 0),
                    'dana_pt' => $bersihkanRupiah($detail['dana_perguruan_tinggi'] ?? 0),
                    'dana_institusi_lain' => $bersihkanRupiah($detail['dana_institusi_lain'] ?? 0),
                    'in_kind' => $detail['in_kind'] ?? null,
                    'mitra' => $detail['mitra'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 3. Simpan atribut tambahan ke `detail_kegiatan`
                $fieldTambahan = ['ranah', 'sebelumnya', 'lokasi', 'nomor_sk', 'tanggal_sk'];
                foreach ($fieldTambahan as $namaField) {
                    if (! empty($detail[$namaField])) {
                        $isianKategori = DB::table('isian_kategori')
                            ->where('nama_isian', $namaField)
                            ->first();

                        if ($isianKategori) {
                            DB::table('detail_kegiatan')->insert([
                                'id_kegiatan' => $idKeg,
                                'id_isian_kategori' => $isianKategori->id_isian_kategori,
                                'isian' => $detail[$namaField],
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }

                return $idKeg;
            });

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan kegiatan penelitian berhasil disimpan ke database.',
                'data' => ['id_kegiatan' => $idKegiatan],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $kegiatan = DB::table('kegiatan')->where('id_kegiatan', $id)->first();

        if (! $kegiatan) {
            return response()->json([
                'success' => false,
                'message' => 'Data penelitian tidak ditemukan.',
            ], 404);
        }

        DB::table('kegiatan')->where('id_kegiatan', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data penelitian berhasil dihapus.',
        ], 200);
    }
}
