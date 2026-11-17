<?php

namespace App\BackendKel3\Profil;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'nama.string'   => 'Nama harus berupa teks.',
            'nama.max'      => 'Nama maksimal 255 karakter.',
        ];
    }
}