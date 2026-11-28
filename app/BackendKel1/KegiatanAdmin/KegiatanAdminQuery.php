<?php

namespace App\BackendKel1\KegiatanAdmin;

use App\Models\Kegiatan;
use App\Models\User;
use App\Support\LinkFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Query + format baris "Data Kegiatan" admin/kaprodi.
 * Aturan: hanya kegiatan yang status TERAKHIR di timeline = 'diterima'.
 * Untuk kegiatan yang dicatat Admin/Kaprodi, jenis mengikuti kategori.
 */
class KegiatanAdminQuery
{
    public const ROLE_JENIS = ['prodi' => [1, 2], 'dosen' => [3], 'mahasiswa' => [4]];

    private const KATEGORI_PENCATAT = ['dosen' => 'Kegiatan Dosen', 'mahasiswa' => 'Kegiatan Mahasiswa'];

    private const RELASI = [
        'kategori.jenisKegiatan',
        'user.dosen',
        'user.mahasiswa',
        'detail.isianKategori',
        'statusTerkini',
    ];

    public static function dasar(?string $jenis = null, ?int $idKategori = null, ?string $q = null): Builder
    {
        return Kegiatan::with(self::RELASI)
            ->whereHas('statusTerkini', fn ($s) => $s->where('status', 'diterima'))
            ->when(isset(self::ROLE_JENIS[$jenis]), fn (Builder $b) => self::filterJenis($b, $jenis))
            ->when($idKategori, fn (Builder $b) => $b->where('id_kategori', $idKategori))
            ->when($q, fn (Builder $b) => $b->where('nama_kegiatan', 'like', '%'.addcslashes($q, '%_\\').'%'));
    }

    private static function filterJenis(Builder $query, string $jenis): Builder
    {
        $pencatat = fn (Builder $u) => $u->whereIn('id_role', self::ROLE_JENIS['prodi']);

        if ($jenis === 'prodi') {
            return $query->whereHas('user', $pencatat)
                ->whereDoesntHave('kategori.jenisKegiatan', fn (Builder $k) => $k->whereIn('nama_jenis_kegiatan', array_values(self::KATEGORI_PENCATAT)));
        }

        return $query->where(fn (Builder $b) => $b
            ->whereHas('user', fn (Builder $u) => $u->whereIn('id_role', self::ROLE_JENIS[$jenis]))
            ->orWhere(fn (Builder $b) => $b->whereHas('user', $pencatat)
                ->whereHas('kategori.jenisKegiatan', fn (Builder $k) => $k->where('nama_jenis_kegiatan', self::KATEGORI_PENCATAT[$jenis]))));
    }

    public static function jenisDariRole(?int $role): string
    {
        foreach (self::ROLE_JENIS as $jenis => $roles) {
            if (in_array((int) $role, $roles, true)) {
                return $jenis;
            }
        }

        return 'prodi';
    }

    /** Ganjil: Sep-Feb, Genap: Mar-Agu. Tabel tahun_akademik tidak punya rentang tanggal, jadi semester dihitung dari bulan. */
    public static function semester(?Carbon $tanggal): string
    {
        if (! $tanggal) {
            return '';
        }

        return ($tanggal->month >= 9 || $tanggal->month <= 2) ? 'Ganjil' : 'Genap';
    }

    /** Tingkat = tahun akademik saat kegiatan - tahun_masuk + 1 (tahun akademik mulai bulan 9). */
    public static function tingkat($tahunMasuk, ?Carbon $tanggal): string
    {
        if (! $tahunMasuk || ! $tanggal) {
            return '';
        }
        $awal = $tanggal->month >= 9 ? $tanggal->year : $tanggal->year - 1;

        return (string) max(1, $awal - (int) $tahunMasuk + 1);
    }

    /** Bentuk baris sama persis dengan KaprodiKegiatanController::petakan() di sisi web. */
    public static function baris(Kegiatan $k): array
    {
        /** @var User|null $user */
        $user = $k->user;
        $jenis = self::jenisDariRole($user?->id_role);
        $pencatat = in_array((int) $user?->id_role, self::ROLE_JENIS['prodi'], true);
        if ($pencatat) {
            $jenis = array_search($k->kategori?->jenisKegiatan?->nama_jenis_kegiatan, self::KATEGORI_PENCATAT, true) ?: 'prodi';
        }
        $tanggal = $k->tanggal_mulai ?? $k->statusTerkini?->tanggal_status;

        $pengaju = $pencatat
            ? ($user?->email ?? '-')
            : ($user?->dosen?->nama ?? $user?->mahasiswa?->nama ?? $user?->email ?? '-');

        return [
            'id' => (string) $k->id_kegiatan,
            'jenis' => $jenis,
            'nama' => $k->nama_kegiatan,
            'id_kategori' => $k->id_kategori,
            'kategori' => $k->kategori?->nama_kategori ?? '-',
            'pengaju' => $pengaju,
            'dicatat_tanpa_pemilik' => $pencatat && $jenis !== 'prodi',
            'tanggal' => $tanggal ? $tanggal->copy()->locale('id')->translatedFormat('j F Y') : '-',
            'raw_date' => $tanggal?->format('Y-m-d') ?? '',
            'tahun' => $tanggal ? (int) $tanggal->format('Y') : '',
            'status' => 'Disetujui',
            'semester' => self::semester($tanggal),
            'tingkat' => self::tingkat($user?->mahasiswa?->tahun_masuk, $tanggal),
            'kelas' => '', // kolom kelas tidak ada di tabel mahasiswa
        ];
    }

    public static function detail(Kegiatan $k): array
    {
        $k->loadMissing([
            'detail.isianKategori',
            'anggota.user.dosen',
            'anggota.user.mahasiswa',
            'dokumen.jenisDokumen',
            'timeline',
        ]);

        return self::baris($k) + [
            'nomor_sk' => $k->nomor_sk,
            'tanggal_sk' => $k->tanggal_sk?->toDateString(),
            'isian' => $k->detail->map(fn ($d) => [
                'input' => $d->isianKategori?->input,
                'isian' => $d->isian,
            ])->values()->all(),
            'anggota' => $k->anggota->map(fn ($a) => [
                'id_user' => $a->id_user,
                'nama' => $a->user?->dosen?->nama ?? $a->user?->mahasiswa?->nama ?? $a->user?->email ?? '-',
                'tipe' => self::jenisDariRole($a->user?->id_role),
                'peran' => $a->peran,
                'status' => ucfirst((string) $a->peran),
                'pengaju' => (int) $a->id_user === (int) $k->id_user,
            ])->values()->all(),
            'dokumen' => $k->dokumen->map(fn ($d) => [
                'id' => $d->id_dokumen,
                'nama' => $d->nama_file,
                'kategori' => $d->jenisDokumen?->nama_jenis_dokumen ?? '-',
                'tipe' => $d->tipe_dokumen,
                'lampiran' => (bool) $d->is_lampiran,
                'url' => self::urlDokumen($d->link_dokumen),
            ])->values()->all(),
            'riwayat' => $k->timeline->map(fn ($t) => [
                'status' => ucfirst((string) $t->status),
                'tanggal' => $t->tanggal_status?->copy()->locale('id')->translatedFormat('j F Y'),
                'keterangan' => $t->catatan_revisi,
            ])->values()->all(),
        ];
    }

    /** Tautan luar dipakai apa adanya; berkas unggahan diubah ke URL publik. */
    private static function urlDokumen(?string $link): ?string
    {
        if (! $link) {
            return null;
        }

        return preg_match('#^https?://#i', $link) ? $link : LinkFile::url($link);
    }
}
