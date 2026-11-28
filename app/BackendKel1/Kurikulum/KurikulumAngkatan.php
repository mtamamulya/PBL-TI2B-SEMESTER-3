<?php

namespace App\BackendKel1\Kurikulum;

use App\Models\Kurikulum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Menentukan kurikulum yang dipakai satu angkatan (tahun masuk mahasiswa).
 *
 * Aturan: kurikulum dengan "tahun mulai" terbesar yang masih <= angkatan.
 *   Contoh: kurikulum A mulai 2024 dan B mulai 2026 -> angkatan 2024 dan 2025 memakai A,
 *   angkatan 2026 ke atas memakai B, angkatan 2023 ke bawah tidak punya kurikulum (hasil kosong).
 *   Status aktif/nonaktif TIDAK dipakai, karena angkatan lama tetap memakai kurikulumnya.
 *
 * "Tahun mulai" dibaca dari kolom yang ada di tabel `kurikulum`:
 *   - sekarang  : tahun_terbit
 *   - bila kelak ada kolom angkatan_mulai (usulan B): coalesce(angkatan_mulai, tahun_terbit),
 *     jadi kurikulum lama yang belum diisi angkatan_mulai tetap memakai tahun_terbit.
 * Perpindahan A -> B hanya menambah kolom di database; kelas ini dan kontrak API tidak berubah.
 * Bila tidak ada kolom tahun sama sekali, hasilnya selalu kosong (tidak error).
 */
class KurikulumAngkatan
{
    /** Ekspresi SQL untuk "tahun mulai", atau null bila tabelnya belum punya kolom tahun. */
    public static function ekspresiMulai(): ?string
    {
        $punyaAngkatanMulai = Schema::hasColumn('kurikulum', 'angkatan_mulai');
        $punyaTahunTerbit = Schema::hasColumn('kurikulum', 'tahun_terbit');

        if ($punyaAngkatanMulai && $punyaTahunTerbit) {
            return 'coalesce(angkatan_mulai, tahun_terbit)';
        }
        if ($punyaAngkatanMulai) {
            return 'angkatan_mulai';
        }
        if ($punyaTahunTerbit) {
            return 'tahun_terbit';
        }

        return null;
    }

    /** id_kurikulum yang dipakai angkatan itu, atau null bila tidak ada. */
    public static function idUntukAngkatan(int $angkatan, ?int $idProdi = null, ?string $kodeProdi = null): ?int
    {
        $mulai = self::ekspresiMulai();
        if ($mulai === null) {
            return null;
        }

        $id = Kurikulum::query()
            ->when($idProdi !== null, fn (Builder $query) => $query->where('id_prodi', $idProdi))
            ->when($kodeProdi !== null, fn (Builder $query) => $query->whereHas(
                'prodi',
                fn (Builder $prodi) => $prodi->where('kode_prodi', $kodeProdi)
            ))
            ->whereRaw($mulai.' <= ?', [$angkatan])
            ->orderByRaw($mulai.' desc')
            ->orderByDesc('id_kurikulum')
            ->value('id_kurikulum');

        return $id === null ? null : (int) $id;
    }

    /**
     * Batasi query ke kurikulum angkatan itu. Angkatan tanpa kurikulum -> query tanpa hasil,
     * jadi respons tetap 200 dengan data kosong dan format paginasi yang sama.
     *
     * @param  Builder  $query  query Kurikulum, atau query MataKuliah (kolom id kurikulumnya = $kolomId)
     */
    public static function saring(
        Builder $query,
        int $angkatan,
        string $kolomId = 'id_kurikulum',
        ?int $idProdi = null,
        ?string $kodeProdi = null,
    ): Builder {
        $id = self::idUntukAngkatan($angkatan, $idProdi, $kodeProdi);

        return $id === null ? $query->whereRaw('1 = 0') : $query->where($kolomId, $id);
    }
}
