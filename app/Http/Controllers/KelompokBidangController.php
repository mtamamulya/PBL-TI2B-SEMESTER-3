<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class KelompokBidangController extends Controller
{
    public function index(Request $request)
    {
        // Deteksi peran dari route agar navbar/sidebar menyesuaikan
        $isKaprodi = $request->routeIs('kaprodi.*');
        $prefix = $isKaprodi ? 'kaprodi' : 'admin';
        $layout = $isKaprodi ? 'layouts.kaprodi' : 'layouts.admin';

        // DATA DUMMY (Sementara untuk keperluan pembuatan Frontend)
        $rows = [
            [
                'id' => 1,
                'nama_kelompok_bidang' => 'Rekayasa Perangkat Lunak',
                'id_rujukan' => null,
            ],
            [
                'id' => 2,
                'nama_kelompok_bidang' => 'Sistem Informasi',
                'id_rujukan' => 1,
            ],
            [
                'id' => 3,
                'nama_kelompok_bidang' => 'Jaringan Komputer',
                'id_rujukan' => null,
            ],
        ];

        $apiError = null;

        // Mengarahkan ke file resources/views/admin/kelompok-bidang.blade.php
        return view('admin.kelompok-bidang', compact('rows', 'prefix', 'layout', 'apiError'));
    }

    public function store(Request $request)
    {
        // Respon statis sukses
        return response()->json([
            'ok' => true,
            'message' => 'Dummy: Data berhasil disimpan.',
        ]);
    }

    public function update(Request $request, $id)
    {
        // Respon statis sukses
        return response()->json([
            'ok' => true,
            'message' => 'Dummy: Data berhasil diperbarui.',
        ]);
    }

    public function destroy($id)
    {
        // Respon statis sukses
        return response()->json([
            'ok' => true,
            'message' => 'Dummy: Data berhasil dihapus.',
        ]);
    }
}
