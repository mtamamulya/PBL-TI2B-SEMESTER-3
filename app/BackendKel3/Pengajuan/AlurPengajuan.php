<?php

namespace App\BackendKel3\Pengajuan;

use App\Models\Kegiatan;
use App\Models\Timeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlurPengajuan
{
    public const STATUS_DIAJUKAN = 'diajukan';
    public const STATUS_DIREVIEW = 'direview';
    public const STATUS_DIREVISI = 'direvisi';
    public const STATUS_DITOLAK  = 'ditolak';
    public const STATUS_DITERIMA = 'diterima';
    public const STATUS_SELESAI  = 'selesai';

    /**
     * Mengajukan kegiatan pertama kali.
     */
    public function ajukanBaru(int $idKegiatan, int $idDosen): Timeline
    {
        return DB::transaction(function () use ($idKegiatan, $idDosen) {
            // Kunci record kegiatan untuk mencegah race condition / klik ganda
            $kegiatan = Kegiatan::where('id_kegiatan', $idKegiatan)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validasiKepemilikan($kegiatan, $idDosen);
            $this->validasiKelengkapan($kegiatan);

            // Pastikan kegiatan belum pernah memiliki riwayat tahap pengajuan
            $sudahAdaTimeline = Timeline::where('id_kegiatan', $idKegiatan)
                ->lockForUpdate()
                ->exists();

            if ($sudahAdaTimeline) {
                throw ValidationException::withMessages([
                    'status' => 'Kegiatan ini sudah pernah diajukan sebelumnya.',
                ]);
            }

            return Timeline::create([
                'id_kegiatan' => $kegiatan->id_kegiatan,
                'status'      => self::STATUS_DIAJUKAN,
                'catatan'     => null,
                'id_aktor'    => $idDosen,
            ]);
        });
    }

    /**
     * Mengajukan ulang kegiatan yang berstatus direvisi.
     */
    public function ajukanUlang(int $idKegiatan, int $idDosen, ?string $catatanPerbaikan = null): Timeline
    {
        return DB::transaction(function () use ($idKegiatan, $idDosen, $catatanPerbaikan) {
            $kegiatan = Kegiatan::where('id_kegiatan', $idKegiatan)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validasiKepemilikan($kegiatan, $idDosen);
            $this->validasiKelengkapan($kegiatan);

            // Ambil status terakhir dengan lock untuk mencegah pengiriman ganda bersamaan
            $statusTerakhir = Timeline::where('id_kegiatan', $idKegiatan)
                ->orderByDesc('id') // atau orderByDesc('created_at')
                ->lockForUpdate()
                ->first();

            if (!$statusTerakhir || $statusTerakhir->status !== self::STATUS_DIREVISI) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan ulang hanya dapat dilakukan jika status terakhir adalah direvisi.',
                ]);
            }

            // Menambah baris baru tanpa menghapus atau menimpa catatan revisi sebelumnya
            return Timeline::create([
                'id_kegiatan' => $kegiatan->id_kegiatan,
                'status'      => self::STATUS_DIAJUKAN,
                'catatan'     => $catatanPerbaikan,
                'id_aktor'    => $idDosen,
            ]);
        });
    }

    /**
     * Memastikan hanya dosen pemilik kegiatan yang dapat mengajukan.
     */
    private function validasiKepemilikan(Kegiatan $kegiatan, int $idDosen): void
    {
        // Sesuaikan nama kolom pemilik di tabel kegiatan (misal: id_dosen atau user_id)
        if ((int) $kegiatan->id_dosen !== $idDosen) {
            abort(403, 'Anda tidak memiliki akses untuk mengajukan kegiatan ini.');
        }
    }

    /**
     * Memeriksa kelengkapan kategori dan lampiran sebelum diajukan.
     */
    private function validasiKelengkapan(Kegiatan $kegiatan): void
    {
        if (empty($kegiatan->kategori)) {
            throw ValidationException::withMessages([
                'kategori' => 'Kategori kegiatan wajib diisi sebelum diajukan.',
            ]);
        }

        // Sesuaikan pengecekan lampiran dengan struktur relasi/kolom di model Kegiatan
        $punyaLampiran = !empty($kegiatan->lampiran) || (method_exists($kegiatan, 'lampirans') && $kegiatan->lampirans()->exists());

        if (!$punyaLampiran) {
            throw ValidationException::withMessages([
                'lampiran' => 'Lampiran dokumen wajib dilengkapi sebelum diajukan.',
            ]);
        }
    }
}