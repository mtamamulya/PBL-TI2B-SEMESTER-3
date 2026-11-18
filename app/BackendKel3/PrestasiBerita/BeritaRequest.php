<?php

namespace App\BackendKel3\PrestasiBerita;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BeritaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'id_kegiatan'    => ['nullable', 'integer', 'exists:kegiatan,id_kegiatan'],
            'judul_berita'   => ["{$required}", 'string', 'max:255'],
            'isi_berita'     => ["{$required}", 'string'],
            'sampul'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status'         => ['nullable', Rule::in(['draft', 'publish'])],
            'catatan_revisi' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'judul_berita.required' => 'Judul berita wajib diisi.',
            'judul_berita.max' => 'Judul berita maksimal 255 karakter.',
            'isi_berita.required' => 'Isi berita wajib diisi.',
            'sampul.image' => 'Sampul harus berupa gambar.',
            'sampul.mimes' => 'Sampul harus berformat jpg, jpeg, png, atau webp.',
            'sampul.max' => 'Ukuran sampul maksimal 2 MB.',
            'status.in' => 'Status harus draft atau publish.',
        ];
    }
}