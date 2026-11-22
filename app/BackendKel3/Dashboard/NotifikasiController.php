<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Timeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NotifikasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()?->id_user ?? 0;

        // Pastikan hanya timeline milik kegiatan dosen yang sedang login (tidak bocor ke dosen lain)
        $notifikasi = Timeline::with('kegiatan')
            ->whereHas('kegiatan', fn ($q) => $q->where('id_user', $userId))
            ->orderByDesc('tanggal_status')
            ->orderByDesc('id_timeline')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                $kegiatan = $item->kegiatan;
                $judul = $kegiatan?->nama_kegiatan ?? $kegiatan?->judul ?? 'Kegiatan #' . $item->id_kegiatan;

                return [
                    'id_timeline' => $item->id_timeline,
                    'id_kegiatan' => $item->id_kegiatan,
                    'judul_kegiatan' => $judul,
                    'status' => $item->status,
                    'catatan_revisi' => $item->catatan_revisi,
                    'waktu' => $item->tanggal_status
                        ? Carbon::parse($item->tanggal_status)->toIso8601String()
                        : null,
                    'waktu_human' => $item->tanggal_status
                        ? Carbon::parse($item->tanggal_status)->locale('id')->diffForHumans()
                        : '-',
                    'tautan_detail' => route('dosen.kegiatan.show', $item->id_kegiatan),
                ];
            });

        return response()->json([
            'status' => 'success',
            'total' => $notifikasi->count(),
            'data' => $notifikasi,
        ]);
    }
}