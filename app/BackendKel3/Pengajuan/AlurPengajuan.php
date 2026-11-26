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
            $kegiatan = Kegiatan::with(['kategori.isianKategori', 'detail.dokumen', 'dokumen', 'anggota'])
                ->where('id_kegiatan', $idKegiatan)
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
            $kegiatan = Kegiatan::with(['kategori.isianKategori', 'detail.dokumen', 'dokumen', 'anggota'])
                ->where('id_kegiatan', $idKegiatan)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validasiKepemilikan($kegiatan, $idUser);
            $this->validasiKelengkapan($kegiatan);

            $statusTerakhir = Timeline::where('id_kegiatan', $kegiatan->id_kegiatan)
                ->orderByDesc('tanggal_status')
                ->orderByDesc('id_timeline')
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
        if ((int) $kegiatan->id_user !== $idUser) {
            abort(403, 'Anda tidak memiliki akses untuk mengajukan kegiatan ini.');
        }
    }

    private function validasiKelengkapan(Kegiatan $kegiatan): void
    {
        $errors = [];

        if (empty($kegiatan->id_kategori) || ! $kegiatan->kategori) {
            throw ValidationException::withMessages([
                'id_kategori' => 'Kategori kegiatan wajib diisi sebelum diajukan.',
            ]);
        }

        if (blank($kegiatan->nama_kegiatan)) {
            $errors['nama_kegiatan'] = 'Nama kegiatan wajib diisi sebelum diajukan.';
        }

        // 1. Validasi setiap isian wajib berdasarkan aturan di tabel isian_kategori
        $detailByIsian = $kegiatan->detail->groupBy('id_isian_kategori');

        foreach ($kegiatan->kategori->isianKategori as $isianKategori) {
            $aturan = strtoupper(trim((string) ($isianKategori->aturan ?? '')));
            $isWajib = $aturan === 'NOT NULL'
                || str_contains($aturan, 'REQUIRED')
                || str_contains($aturan, 'WAJIB');

            if (! $isWajib) {
                continue;
            }

            $label = ucwords(str_replace('_', ' ', (string) $isianKategori->input));
            $key = 'isian.'.$isianKategori->input;
            $daftarDetail = $detailByIsian->get($isianKategori->id_isian_kategori, collect());
            $jenisInput = strtolower((string) ($isianKategori->jenis_input ?? ''));

            if ($jenisInput === 'file') {
                $punyaFile = $daftarDetail->contains(function ($detail) {
                    return $detail->dokumen()->exists() || filled($detail->isian);
                });

                if (! $punyaFile) {
                    $errors[$key] = "Lampiran wajib '{$label}' untuk kategori {$kegiatan->kategori->nama_kategori} belum diunggah.";
                }
            } else {
                $terisi = $daftarDetail->contains(fn ($detail) => filled($detail->isian));

                if (! $terisi) {
                    $errors[$key] = "Isian wajib '{$label}' untuk kategori {$kegiatan->kategori->nama_kategori} belum diisi.";
                }
            }
        }

        // 2. Validasi lampiran peserta jika kategori mewajibkannya
        if ($kegiatan->kategori->lampiran_peserta) {
            $adaPeserta = $kegiatan->anggota()->exists()
                || $kegiatan->dokumen()->where('is_lampiran', true)->exists();

            if (! $adaPeserta) {
                $errors['lampiran_peserta'] = "Kategori {$kegiatan->kategori->nama_kategori} mewajibkan data atau lampiran peserta.";
            }
        }

        // 3. Pastikan minimal terdapat satu dokumen pendukung pada kegiatan
        if (! $kegiatan->dokumen()->exists()) {
            $errors['dokumen'] = 'Lampiran dokumen wajib dilengkapi sebelum diajukan.';
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
