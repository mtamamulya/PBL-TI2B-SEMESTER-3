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
    public const STATUS_DITOLAK = 'ditolak';
    public const STATUS_DITERIMA = 'diterima';
    public const STATUS_SELESAI = 'selesai';

    public function ajukanBaru(int $idKegiatan, int $idUser): Timeline
    {
        return DB::transaction(function () use ($idKegiatan, $idUser) {
            $kegiatan = Kegiatan::where('id_kegiatan', $idKegiatan)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validasiKepemilikan($kegiatan, $idUser);
            $this->validasiKelengkapan($kegiatan);

            $sudahAdaRiwayat = Timeline::where('id_kegiatan', $kegiatan->id_kegiatan)->exists();

            if ($sudahAdaRiwayat) {
                throw ValidationException::withMessages([
                    'status' => 'Kegiatan ini sudah pernah diajukan. Gunakan fitur ajukan ulang jika statusnya direvisi.',
                ]);
            }

            return Timeline::create([
                'id_kegiatan' => $kegiatan->id_kegiatan,
                'status' => self::STATUS_DIAJUKAN,
                'tanggal_status' => now(),
                'catatan_revisi' => null,
            ]);
        });
    }

    public function ajukanUlang(int $idKegiatan, int $idUser, ?string $catatan = null): Timeline
    {
        return DB::transaction(function () use ($idKegiatan, $idUser, $catatan) {
            $kegiatan = Kegiatan::where('id_kegiatan', $idKegiatan)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validasiKepemilikan($kegiatan, $idUser);
            $this->validasiKelengkapan($kegiatan);

            $statusTerakhir = Timeline::where('id_kegiatan', $kegiatan->id_kegiatan)
                ->orderByDesc('tanggal_status')
                ->orderByDesc('id_timeline') // Asumsi primary key tabel timeline adalah id_timeline
                ->first();

            if (! $statusTerakhir || $statusTerakhir->status !== self::STATUS_DIREVISI) {
                throw ValidationException::withMessages([
                    'status' => 'Pengajuan ulang hanya dapat dilakukan ketika status terkini adalah direvisi.',
                ]);
            }

            return Timeline::create([
                'id_kegiatan' => $kegiatan->id_kegiatan,
                'status' => self::STATUS_DIAJUKAN,
                'tanggal_status' => now(),
                'catatan_revisi' => $catatan, 
            ]);
        });
    }

    private function validasiKepemilikan(Kegiatan $kegiatan, int $idUser): void
    {
        // Mencocokkan id_user yang sedang login dengan id_user pembuat di tabel kegiatan[cite: 7]
        if ((int) $kegiatan->id_user !== $idUser) { 
            abort(403, 'Anda tidak memiliki akses untuk mengajukan kegiatan ini.');
        }
    }

    private function validasiKelengkapan(Kegiatan $kegiatan): void
    {
        // Memastikan id_kategori sudah terisi[cite: 7]
        if (empty($kegiatan->id_kategori)) {
            throw ValidationException::withMessages([
                'id_kategori' => 'Kategori kegiatan wajib diisi sebelum diajukan.',
            ]);
        }

        // Pastikan model Kegiatan memiliki relasi dokumen() ke tabel dokumen
        if (! $kegiatan->dokumen()->exists()) {
            throw ValidationException::withMessages([
                'dokumen' => 'Lampiran dokumen wajib dilengkapi sebelum diajukan.',
            ]);
        }
    }
}