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
        return [
            'sumber' => ['required', Rule::in(['kegiatan', 'mandiri'])],
            'kegiatan_sebelumnya' => ['nullable', 'string', 'max:255'],
            'kategori' => ['nullable', 'string', 'max:150'],
            'judul' => ['required', 'string', 'max:255'],
            'isi' => ['required', 'string'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'diajukan'])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->filled('isi')) {
                $teksMurni = strip_tags($this->input('isi'));
                if (mb_strlen($teksMurni) > 1000) {
                    $validator->errors()->add('isi', 'Isi berita maksimal 1.000 karakter (tidak termasuk tag HTML).');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'sumber.required' => 'Sumber berita wajib dipilih.',
            'sumber.in' => 'Sumber harus kegiatan atau mandiri.',
            'kegiatan_sebelumnya.max' => 'Nama kegiatan terlalu panjang.',
            'kategori.max' => 'Nama kategori terlalu panjang.',
            'judul.required' => 'Judul berita wajib diisi.',
            'judul.max' => 'Judul berita maksimal 255 karakter.',
            'isi.required' => 'Isi berita wajib diisi.',
            'foto.image' => 'Sampul harus berupa gambar.',
            'foto.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status harus draft atau diajukan.',
        ];
    }
}
