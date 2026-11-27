<?php

namespace App\BackendKel2\Dokumen\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimpanTautanDokumenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_jenis_dokumen' => ['required', 'integer', 'exists:jenis_dokumen,id_jenis_dokumen'],
            'nama_file' => ['required', 'string', 'max:255'],
            'tautan' => ['required', 'string', 'max:2000'],
            'id_detail_kegiatan' => ['nullable', 'integer', 'exists:detail_kegiatan,id_detail_kegiatan'],
        ];
    }
}
