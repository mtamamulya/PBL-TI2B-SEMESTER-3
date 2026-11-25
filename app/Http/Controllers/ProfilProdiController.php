<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Halaman Profil Prodi (admin + kaprodi). Isi halaman menyusul. */
class ProfilProdiController extends Controller
{
    public function index(Request $request)
    {
        $kaprodi = $request->routeIs('kaprodi.*');

        return view('admin.profil-prodi', [
            'prefix' => $kaprodi ? 'kaprodi' : 'admin',
            'layout' => $kaprodi ? 'layouts.kaprodi' : 'layouts.admin',
        ]);
    }
}
