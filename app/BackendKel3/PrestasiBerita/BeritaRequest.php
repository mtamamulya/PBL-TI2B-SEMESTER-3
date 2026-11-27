<?php

namespace App\BackendKel3\PrestasiBerita;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BeritaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['judul' => 'judul_berita', 'isi' => 'isi_berita', 'kegiatan_id' => 'id_kegiatan'] as $old => $new) {
            if (! $this->has($new) && $this->has($old)) {
                $this->merge([$new => $this->input($old)]);
            }
        }
        if (! $this->hasFile('sampul') && $this->hasFile('foto')) {
            $this->files->set('sampul', $this->file('foto'));
        }
        if (! $this->has('status') && $this->input('intent') === 'submit') {
            $this->merge(['status' => 'diajukan']);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'id_kegiatan' => ['nullable', 'prohibits:id_prestasi', 'integer', 'exists:kegiatan,id_kegiatan'],
            'id_prestasi' => ['nullable', 'prohibits:id_kegiatan', 'integer', 'exists:prestasi,id_prestasi'],
            'id_kategori' => ['nullable', 'integer', 'exists:kategori,id_kategori'],
            'judul_berita' => ["{$required}", 'string', 'max:255'],
            'isi_berita' => $this->input('status') === 'draft' ? ['nullable', 'string'] : ["{$required}", 'string'],
            'sampul' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'status' => ['nullable', Rule::in(['draft', 'diajukan'])],

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
            'status.in' => 'Status harus draft atau diajukan.',
        ];
    }
}
