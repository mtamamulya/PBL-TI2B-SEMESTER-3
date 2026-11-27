<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\NotifikasiDibaca;
use App\Models\Timeline;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Sumber notifikasi = tabel timeline pada kegiatan yang boleh dilihat dosen
 * (pemilik atau anggota, definisi yang sama dengan dashboard lewat Kegiatan::untukUser()).
 *
 * Jenis yang bisa dihasilkan dari timeline hanya "pengajuan" dan "tim".
 * Jenis "sistem" tidak punya sumber data, jadi tidak pernah dikirim.
 *
 * Status "sudah dibaca" disimpan per pengguna di tabel notifikasi_dibaca, jadi tetap
 * tersimpan setelah halaman dimuat ulang atau dibuka dari perangkat lain.
 */
class NotifikasiController extends Controller
{
    /**
     * Notifikasi yang lebih tua dari ini tidak lagi dihitung "belum dibaca" walau belum pernah dibuka.
     * Tujuannya agar pengguna lama tidak melihat ratusan titik biru dari riwayat sebelum fitur baca ada.
     */
    private const HARI_BARU = 30;

    /**
     * Waktu di database disimpan UTC (config/app.php timezone = UTC). Untuk tampilan notifikasi
     * dikonversi ke WIB, termasuk penentuan "Hari ini" / "Kemarin", tanpa mengubah zona waktu aplikasi.
     */
    private const ZONA_WAKTU = 'Asia/Jakarta';

    /** GET /api/v1/dosen/notifikasi */
    public function index(Request $request): JsonResponse
    {
        $data = $this->susun($request->user(), 30)->map(fn (array $n) => [
            'id_timeline' => $n['id'],
            'id_kegiatan' => $n['id_kegiatan'],
            'judul_kegiatan' => $n['judul_kegiatan'],
            'jenis' => $n['type'],
            'judul' => $n['title'],
            'isi' => $n['body'],
            'status' => $n['status'],
            'catatan_revisi' => $n['catatan_revisi'],
            'belum_dibaca' => $n['unread'],
            'waktu' => $n['waktu_iso'],
            'waktu_human' => $n['waktu_human'],
            'tautan_detail' => $n['href'],
        ])->values()->all();

        return ApiResponse::ok($data, 'Daftar notifikasi berhasil dimuat.');
    }

    /** GET /notifikasi (halaman web) */
    public function web(Request $request): View
    {
        $notifikasi = $this->susun($request->user(), 50)->map(fn (array $n) => [
            'id' => $n['id'],
            'g' => $n['g'],
            'type' => $n['type'],
            'title' => $n['title'],
            'body' => $n['body'],
            'time' => $n['time'],
            'unread' => $n['unread'],
            'href' => $n['href'],
        ])->values()->all();

        return view('dosen.notifikasi.index', compact('notifikasi'));
    }

    /** Satu sumber data untuk web dan API, supaya keduanya tidak berbeda isi. */
    private function susun(?User $user, int $batas): Collection
    {
        if (! $user) {
            return collect();
        }

        $timelines = Timeline::with('kegiatan')
            ->whereHas('kegiatan', fn ($q) => $q->untukUser($user))
            ->orderByDesc('tanggal_status')
            ->orderByDesc('id_timeline')
            ->limit($batas)
            ->get();

        // id_timeline yang sudah dibuka OLEH USER INI (bukan pemilik/anggota lain).
        $sudahDibaca = NotifikasiDibaca::where('id_user', $user->getKey())
            ->whereIn('id_timeline', $timelines->pluck('id_timeline'))
            ->pluck('id_timeline')
            ->flip();

        return $timelines->map(function (Timeline $item) use ($user, $sudahDibaca) {
            $status = strtolower((string) $item->status);
            $kegiatan = $item->kegiatan;
            $judul = $kegiatan?->nama_kegiatan ?: 'Kegiatan #'.$item->id_kegiatan;
            $waktuRaw = $item->tanggal_status ?? $item->created_at;
            $waktu = $waktuRaw ? Carbon::parse($waktuRaw)->setTimezone(self::ZONA_WAKTU)->locale('id') : null;

            // Bukan pembuat kegiatan, berarti dosen ini anggota tim.
            $isAnggotaTim = $kegiatan && (int) $kegiatan->id_user !== (int) $user->getKey();

            $masihBaru = $waktu ? $waktu->greaterThan(now()->subDays(self::HARI_BARU)) : false;

            return [
                'id' => $item->id_timeline,
                'id_kegiatan' => $item->id_kegiatan,
                'judul_kegiatan' => $judul,
                'status' => $status,
                'catatan_revisi' => $item->catatan_revisi,
                'type' => $isAnggotaTim ? 'tim' : 'pengajuan',
                'title' => $this->judulNotifikasi($status, $isAnggotaTim),
                'body' => $this->isiNotifikasi($status, $judul, $item->catatan_revisi, $isAnggotaTim),
                'g' => $this->kelompokWaktu($waktu),
                'time' => $this->formatWaktu($waktu),
                'unread' => $masihBaru && ! $sudahDibaca->has($item->id_timeline),
                'waktu_iso' => $waktu?->toIso8601String(),
                'waktu_human' => $waktu ? $waktu->diffForHumans() : '-',
                'href' => $this->detailUrl($item->id_kegiatan),
            ];
        });
    }

    /**
     * POST /api/v1/dosen/notifikasi/{idTimeline}/baca  dan  POST /notifikasi/{idTimeline}/baca
     * Idempoten: memanggil dua kali tidak error. Timeline milik dosen lain menghasilkan 404 (tidak bocor).
     */
    public function tandaiDibaca(Request $request, int $idTimeline): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::gagal('Belum login.', 401);
        }

        $boleh = Timeline::whereKey($idTimeline)
            ->whereHas('kegiatan', fn ($q) => $q->untukUser($user))
            ->exists();

        if (! $boleh) {
            return ApiResponse::gagal('Notifikasi tidak ditemukan.', 404);
        }

        NotifikasiDibaca::query()->insertOrIgnore([
            'id_user' => $user->getKey(),
            'id_timeline' => $idTimeline,
            'dibaca_pada' => now(),
        ]);

        return ApiResponse::ok(['id_timeline' => $idTimeline, 'belum_dibaca' => false], 'Notifikasi ditandai sudah dibaca.');
    }

    /** POST /api/v1/dosen/notifikasi/baca-semua  dan  POST /notifikasi/baca-semua */
    public function tandaiSemuaDibaca(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return ApiResponse::gagal('Belum login.', 401);
        }

        $sekarang = now();
        $baris = Timeline::whereHas('kegiatan', fn ($q) => $q->untukUser($user))
            ->pluck('id_timeline')
            ->map(fn ($id) => [
                'id_user' => $user->getKey(),
                'id_timeline' => $id,
                'dibaca_pada' => $sekarang,
            ]);

        foreach ($baris->chunk(500) as $paket) {
            NotifikasiDibaca::query()->insertOrIgnore($paket->values()->all());
        }

        return ApiResponse::ok(['jumlah_ditandai' => $baris->count()], 'Semua notifikasi ditandai sudah dibaca.');
    }

    private function kelompokWaktu(?Carbon $waktu): string
    {
        if (! $waktu) {
            return 'Lebih lama';
        }

        if ($waktu->isToday()) {
            return 'Hari ini';
        }

        if ($waktu->isYesterday()) {
            return 'Kemarin';
        }

        return 'Lebih lama';
    }

    private function formatWaktu(?Carbon $waktu): string
    {
        if (! $waktu) {
            return '-';
        }

        return ($waktu->isToday() || $waktu->isYesterday())
            ? $waktu->format('H.i')
            : $waktu->translatedFormat('j M Y');
    }

    private function judulNotifikasi(string $status, bool $isAnggotaTim): string
    {
        if ($isAnggotaTim && $status === 'diajukan') {
            return 'Anda tergabung dalam tim kegiatan';
        }

        return match ($status) {
            'diajukan' => 'Pengajuan terkirim',
            'direview' => 'Pengajuan sedang direview',
            'direvisi' => 'Pengajuan perlu direvisi',
            'diterima' => 'Pengajuan diterima',
            'ditolak' => 'Pengajuan ditolak',
            'selesai' => 'Kegiatan ditandai selesai',
            default => 'Pembaruan status kegiatan',
        };
    }

    private function isiNotifikasi(string $status, string $judul, ?string $catatan, bool $isAnggotaTim): string
    {
        $teksCatatan = $catatan ? " Catatan: {$catatan}" : '';

        if ($isAnggotaTim && $status === 'diajukan') {
            return "Anda terdaftar sebagai anggota pada kegiatan \"{$judul}\".{$teksCatatan}";
        }

        return match ($status) {
            'diajukan' => "Kegiatan \"{$judul}\" telah diajukan dan menunggu peninjauan.{$teksCatatan}",
            'direview' => "Pengajuan \"{$judul}\" mulai ditinjau oleh admin/kaprodi.{$teksCatatan}",
            'direvisi' => "Kegiatan \"{$judul}\" dikembalikan untuk diperbaiki.{$teksCatatan}",
            'diterima' => "Kegiatan \"{$judul}\" telah diterima.{$teksCatatan}",
            'ditolak' => "Pengajuan \"{$judul}\" ditolak.{$teksCatatan}",
            'selesai' => "Kegiatan \"{$judul}\" telah ditandai selesai.{$teksCatatan}",
            default => "Status kegiatan \"{$judul}\" diperbarui menjadi {$status}.{$teksCatatan}",
        };
    }

    private function detailUrl(?int $idKegiatan): string
    {
        if (! $idKegiatan) {
            return '#';
        }

        return Route::has('dosen.kegiatan.show')
            ? route('dosen.kegiatan.show', $idKegiatan)
            : url("/dosen/kegiatan/{$idKegiatan}");
    }
}
