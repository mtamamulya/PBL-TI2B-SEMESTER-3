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
            'foto_profil' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'foto_profil.required' => 'Pilih file foto profil terlebih dahulu.',
            'foto_profil.image' => 'File foto profil harus berupa gambar.',
            'foto_profil.mimes' => 'Format foto profil harus jpg, jpeg, png, atau webp.',
            'foto_profil.max' => 'Ukuran foto profil maksimal 2 MB.',
        ];
    }
}
