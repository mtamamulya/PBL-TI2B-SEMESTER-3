<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Timeline;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class NotifikasiController extends Controller
{
    /**
     * Endpoint API: GET /api/v1/dosen/notifikasi
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user?->id_user ?? 0;

            $timelines = Timeline::with('kegiatan')
            ->whereHas('kegiatan', fn ($q) => $q->untukUser($user))
            ->orderByDesc('tanggal_status')
            ->orderByDesc('id_timeline')
            ->limit(30)
            ->get();

        $data = $timelines->map(function ($item) {
            $status = strtolower((string) $item->status);
            $waktuRaw = $item->tanggal_status ?? $item->created_at;
            $waktu = $waktuRaw ? Carbon::parse($waktuRaw) : null;
            $judul = $item->kegiatan?->nama_kegiatan ?? $item->kegiatan?->judul ?? 'Kegiatan #' . $item->id_kegiatan;

            return [
                'id_timeline'    => $item->id_timeline,
                'id_kegiatan'    => $item->id_kegiatan,
                'judul_kegiatan' => $judul,
                'status'         => $status,
                'catatan_revisi' => $item->catatan_revisi,
                'waktu'          => $waktu?->toIso8601String(),
                'waktu_human'    => $waktu ? $waktu->locale('id')->diffForHumans() : '-',
                'tautan_detail'  => $this->detailUrl($item->id_kegiatan),
            ];
        });

        return ApiResponse::success($data, 'Daftar notifikasi berhasil dimuat.');
    }

    /**
     * Halaman Web: GET /dosen/notifikasi
     */
    public function web(Request $request): View
    {
        $user = $request->user();
        $userId = $user?->id_user ?? 0;

            $timelines = Timeline::with('kegiatan')
            ->whereHas('kegiatan', fn ($q) => $q->untukUser($user))
            ->orderByDesc('tanggal_status')
            ->orderByDesc('id_timeline')
            ->limit(50)
            ->get();

        $notifikasi = $timelines->map(function ($item) use ($userId) {
            $status = strtolower((string) $item->status);
            $kegiatan = $item->kegiatan;
            $judul = $kegiatan?->nama_kegiatan ?? $kegiatan?->judul ?? 'Kegiatan #' . $item->id_kegiatan;

            $waktuRaw = $item->tanggal_status ?? $item->created_at;
            $waktu = $waktuRaw ? Carbon::parse($waktuRaw)->locale('id') : null;

            // Jika dosen bukan pembuat utama kegiatan, tandai sebagai kategori 'tim'
            $isAnggotaTim = $kegiatan && (int) $kegiatan->id_user !== (int) $userId;
            $type = $isAnggotaTim ? 'tim' : 'pengajuan';

            return [
                'id'     => $item->id_timeline,
                'g'      => $this->kelompokWaktu($waktu),
                'type'   => $type,
                'title'  => $this->judulNotifikasi($status, $isAnggotaTim),
                'body'   => $this->isiNotifikasi($status, $judul, $item->catatan_revisi, $isAnggotaTim),
                'time'   => $this->formatWaktu($waktu),
                'unread' => $waktu ? $waktu->greaterThan(now()->subDays(3)) : false,
                'href'   => $this->detailUrl($item->id_kegiatan),
            ];
        })->values()->all();

        return view('dosen.notifikasi.index', compact('notifikasi'));
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
            'diajukan'            => 'Pengajuan terkirim',
            'direview'            => 'Pengajuan sedang direview',
            'direvisi', 'revisi'  => 'Pengajuan perlu direvisi',
            'diterima', 'disetujui' => 'Pengajuan diterima',
            'ditolak'             => 'Pengajuan ditolak',
            'selesai'             => 'Kegiatan ditandai selesai',
            default               => 'Pembaruan status kegiatan',
        };
    }

    private function isiNotifikasi(string $status, string $judul, ?string $catatan, bool $isAnggotaTim): string
    {
        $teksCatatan = $catatan ? " Catatan: {$catatan}" : '';

        if ($isAnggotaTim && $status === 'diajukan') {
            return "Anda terdaftar sebagai anggota pada kegiatan \"{$judul}\".{$teksCatatan}";
        }

        return match ($status) {
            'diajukan'              => "Kegiatan \"{$judul}\" telah diajukan dan menunggu peninjauan.{$teksCatatan}",
            'direview'              => "Pengajuan \"{$judul}\" mulai ditinjau oleh admin/kaprodi.{$teksCatatan}",
            'direvisi', 'revisi'    => "Kegiatan \"{$judul}\" dikembalikan untuk diperbaiki.{$teksCatatan}",
            'diterima', 'disetujui' => "Kegiatan \"{$judul}\" telah diterima.{$teksCatatan}",
            'ditolak'               => "Pengajuan \"{$judul}\" ditolak.{$teksCatatan}",
            'selesai'               => "Kegiatan \"{$judul}\" telah ditandai selesai.{$teksCatatan}",
            default                 => "Status kegiatan \"{$judul}\" diperbarui menjadi {$status}.{$teksCatatan}",
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