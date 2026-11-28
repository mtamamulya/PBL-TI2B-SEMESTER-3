<?php

namespace App\Http\Controllers;

use App\Support\NotifikasiStaf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifikasi admin + kaprodi (satu controller, satu halaman, beda $prefix/$layout).
 *
 * FASE 1 (sekarang): MODE DUMMY. Data dari App\Support\NotifikasiStaf, status "dibaca" di session.
 * FASE 3: cabang non-dummy diisi panggilan ke API notifikasi Kelompok 1 (dibuat di Fase 2).
 */
class NotifikasiStafController extends Controller
{
    public function index(Request $request)
    {
        [$prefix, $layout] = $this->peran($request);
        $dummy = NotifikasiStaf::modeDummy();
        $this->pastikanDummy();

        $notifikasi = NotifikasiStaf::daftar($prefix, $this->dibaca($request, $prefix));

        return view('admin.notifikasi', compact('notifikasi', 'prefix', 'layout', 'dummy'));
    }

    public function baca(Request $request, string $id): JsonResponse
    {
        [$prefix] = $this->peran($request);
        $this->pastikanDummy();

        // id milik peran lain (atau tidak dikenal) = 404, tidak bocor.
        if (! NotifikasiStaf::idValid($prefix, $id)) {
            return response()->json(['ok' => false, 'message' => 'Notifikasi tidak ditemukan.'], 404);
        }

        $this->simpanDibaca($request, $prefix, [$id]);

        return response()->json(['ok' => true, 'data' => ['id' => $id, 'belum_dibaca' => false]]);
    }

    public function bacaSemua(Request $request): JsonResponse
    {
        [$prefix] = $this->peran($request);
        $this->pastikanDummy();

        $belum = array_values(array_filter(
            NotifikasiStaf::daftar($prefix, $this->dibaca($request, $prefix)),
            fn ($n) => $n['unread']
        ));
        $this->simpanDibaca($request, $prefix, array_column($belum, 'id'));

        return response()->json(['ok' => true, 'data' => ['jumlah_ditandai' => count($belum)]]);
    }

    /** FASE 3: hapus metode ini setelah cabang API terisi. */
    private function pastikanDummy(): void
    {
        if (! NotifikasiStaf::modeDummy()) {
            abort(501, 'API notifikasi belum tersedia (Fase 2).');
        }
    }

    /** @return list<string> */
    private function dibaca(Request $request, string $prefix): array
    {
        return array_values((array) $request->session()->get(NotifikasiStaf::kunciSession($prefix), []));
    }

    /** @param list<string> $ids */
    private function simpanDibaca(Request $request, string $prefix, array $ids): void
    {
        $request->session()->put(
            NotifikasiStaf::kunciSession($prefix),
            array_values(array_unique(array_merge($this->dibaca($request, $prefix), $ids)))
        );
    }

    /** @return array{0:string,1:string} */
    private function peran(Request $request): array
    {
        $kaprodi = $request->routeIs('kaprodi.*');

        return [$kaprodi ? 'kaprodi' : 'admin', $kaprodi ? 'layouts.kaprodi' : 'layouts.admin'];
    }
}
