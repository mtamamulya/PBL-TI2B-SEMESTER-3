<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class ApiDokumentasiController extends Controller
{
    private function izinkan(): void
    {
        abort_unless(config('api_docs.publik') || app()->isLocal(), 404);
    }

    public function ui()
    {
        $this->izinkan();

        return view('api-docs');
    }

    public function spec(): Response
    {
        $this->izinkan();

        return response(file_get_contents(config('api_docs.file')), 200, [
            'Content-Type' => 'application/yaml; charset=UTF-8',
        ]);
    }
}
