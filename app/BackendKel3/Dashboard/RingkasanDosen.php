<?php

namespace App\BackendKel3\Dashboard;

use App\Models\Dosen;
use App\Models\EnrollmentKelasMataKuliah;
use App\Models\Kegiatan;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Satu-satunya tempat yang menghitung angka dashboard dosen.
 * Dipakai bersama oleh DosenDashboardController (web) dan DashboardApiController (API)
 * supaya kartu, daftar, dan JSON selalu memakai definisi yang sama.
 */
class RingkasanDosen
{
    /** Kegiatan belum diajukan: belum punya baris timeline sama sekali. */
    public const STATUS_DRAF = 'draf';

    /** Status yang dihitung sebagai "pengajuan menunggu". Draf sengaja tidak termasuk. */
    public const STATUS_MENUNGGU = ['diajukan', 'direview', 'direvisi'];

    public const STATUS_PERLU_REVISI = ['direvisi'];

    /** Jumlah tahun pada grafik, termasuk tahun berjalan. */
    public const RENTANG_GRAFIK = 7;

    /**
     * Kegiatan milik user (sebagai pemilik ATAU anggota), lengkap dengan status terakhir.
     * Definisi "milik dosen" ini juga dipakai notifikasi lewat scope Kegiatan::untukUser().
     */
    public function kegiatan(?User $user): Collection
    {
        return Kegiatan::untukUser($user)
            ->with([
                'kategori',
                'detail.isianKategori', // untuk accessor tanggal_mulai, hindari N+1
                'timeline' => fn ($q) => $q->reorder()->orderByDesc('tanggal_status')->orderByDesc('id_timeline'),
            ])
            ->latest('id_kegiatan')
            ->get()
            ->map(function (Kegiatan $item) {
                $terakhir = $item->timeline->first();

                $item->status_terakhir = $terakhir ? strtolower((string) $terakhir->status) : self::STATUS_DRAF;
                $item->tanggal_status_terakhir = $terakhir?->tanggal_status ?? $item->created_at;
                $item->catatan_terakhir = $terakhir?->catatan_revisi;

                return $item;
            });
    }

    /**
     * "penelitian" / "pengabdian" / null.
     *
     * Catatan: tabel jenis_kegiatan hanya berisi 4 nilai (Kegiatan Dosen, Mahasiswa, Prodi, ...),
     * jadi TIDAK bisa membedakan penelitian dan pengabdian. Satu-satunya pembeda adalah
     * kategori.nama_kategori. Aturannya dipusatkan di sini agar cukup diubah di satu tempat.
     */
    public function jenis(Kegiatan $kegiatan): ?string
    {
        $nama = trim(mb_strtolower((string) $kegiatan->kategori?->nama_kategori));

        foreach (['penelitian', 'pengabdian'] as $jenis) {
            if (str_starts_with($nama, $jenis)) {
                return $jenis;
            }
        }

        return null;
    }

    public function hitungJenis(Collection $kegiatan, string $jenis): int
    {
        return $kegiatan->filter(fn (Kegiatan $k) => $this->jenis($k) === $jenis)->count();
    }

    public function menunggu(Collection $kegiatan): Collection
    {
        return $kegiatan->whereIn('status_terakhir', self::STATUS_MENUNGGU)->values();
    }

    public function perluRevisi(Collection $kegiatan): int
    {
        return $kegiatan->whereIn('status_terakhir', self::STATUS_PERLU_REVISI)->count();
    }

    public function judul(Kegiatan $kegiatan): string
    {
        return $kegiatan->nama_kegiatan ?: 'Kegiatan #'.$kegiatan->id_kegiatan;
    }

    /** @return array<string,int> tahun => jumlah kegiatan (berdasar tanggal_mulai, cadangan created_at) */
    public function grafikPerTahun(Collection $kegiatan): array
    {
        $sekarang = (int) now()->year;
        $grafik = [];
        for ($tahun = $sekarang - self::RENTANG_GRAFIK + 1; $tahun <= $sekarang; $tahun++) {
            $grafik[(string) $tahun] = 0;
        }

        foreach ($kegiatan as $item) {
            $acuan = $item->tanggal_mulai ?? $item->created_at;
            if ($acuan) {
                $tahun = (string) Carbon::parse($acuan)->year;
                if (array_key_exists($tahun, $grafik)) {
                    $grafik[$tahun]++;
                }
            }
        }

        return $grafik;
    }

    /**
     * Tahun akademik yang dipakai untuk menghitung pengajaran.
     * Urutan: id yang diminta (bila ada) -> yang berstatus aktif -> yang terbaru.
     * Aturan ini sama untuk web dan API, dan TIDAK PERNAH jatuh ke "semua tahun".
     */
    public function tahunAkademik(?int $idDiminta = null): ?TahunAkademik
    {
        if ($idDiminta) {
            $dipilih = TahunAkademik::find($idDiminta);
            if ($dipilih) {
                return $dipilih;
            }
        }

        return TahunAkademik::where('status', true)->orderByDesc('id_tahun_akademik')->first()
            ?? TahunAkademik::orderByDesc('id_tahun_akademik')->first();
    }

    /**
     * Penugasan mengajar = baris enrollment_kelas_mata_kuliah milik dosen pada tahun akademik terpilih.
     * Satu baris = satu kelas pada satu mata kuliah (definisi yang sama dengan daftar Pengajaran Naya).
     */
    public function kelasDiampu(?Dosen $dosen, ?TahunAkademik $tahun, bool $denganRelasi = false): Collection
    {
        if (! $dosen) {
            return collect();
        }

        $query = EnrollmentKelasMataKuliah::query()
            ->where('id_dosen', $dosen->id_dosen)
            ->when($tahun, fn ($q) => $q->whereHas(
                'enrollmentMataKuliah',
                fn ($sub) => $sub->where('id_tahun_akademik', $tahun->id_tahun_akademik)
            ));

        if ($denganRelasi) {
            $query->with([
                'enrollmentMataKuliah.mataKuliah',
                'enrollmentMataKuliah.tahunAkademik',
                'enrollmentTingkatKelas.tingkat',
                'enrollmentTingkatKelas.kelas',
                'enrollmentTingkatKelas.prodi',
            ]);
        }

        return $query->get();
    }
}
