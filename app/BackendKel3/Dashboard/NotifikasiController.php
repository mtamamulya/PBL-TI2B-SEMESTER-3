<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
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
 */
class NotifikasiController extends Controller
{
    /** Notifikasi dianggap baru selama 3 hari. Belum ada penyimpanan "sudah dibaca" per pengguna. */
    private const HARI_BARU = 3;

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

        return Timeline::with('kegiatan')
            ->whereHas('kegiatan', fn ($q) => $q->untukUser($user))
            ->orderByDesc('tanggal_status')
            ->orderByDesc('id_timeline')
            ->limit($batas)
            ->get()
            ->map(function (Timeline $item) use ($user) {
                $status = strtolower((string) $item->status);
                $kegiatan = $item->kegiatan;
                $judul = $kegiatan?->nama_kegiatan ?: 'Kegiatan #'.$item->id_kegiatan;
                $waktuRaw = $item->tanggal_status ?? $item->created_at;
                $waktu = $waktuRaw ? Carbon::parse($waktuRaw)->locale('id') : null;

                // Bukan pembuat kegiatan, berarti dosen ini anggota tim.
                $isAnggotaTim = $kegiatan && (int) $kegiatan->id_user !== (int) $user->getKey();

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
                    'unread' => $waktu ? $waktu->greaterThan(now()->subDays(self::HARI_BARU)) : false,
                    'waktu_iso' => $waktu?->toIso8601String(),
                    'waktu_human' => $waktu ? $waktu->diffForHumans() : '-',
                    'href' => $this->detailUrl($item->id_kegiatan),
                ];
            });
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
