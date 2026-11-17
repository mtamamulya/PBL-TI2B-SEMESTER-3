<?php

namespace App\BackendKel3\PrestasiBerita;

use Illuminate\Foundation\Http\FormRequest;

class PrestasiRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['tingkat', 'jenis', 'bentuk'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtolower($this->input($field))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'sumber' => 'required|in:kegiatan,mandiri',
            'id_kegiatan' => 'nullable|required_if:sumber,kegiatan|integer|exists:kegiatan,id_kegiatan',
            'nama_prestasi' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:150',
            'tingkat' => 'required|in:kabupaten/kota,provinsi,nasional,internasional',
            'jenis' => 'required|in:akademik,non-akademik',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'capaian' => 'required|string|max:100',
            'kategori' => 'nullable|string|max:150',
            'bentuk' => 'required|in:individu,tim',
            'lokasi' => 'nullable|string|max:150',
            'deskripsi' => 'nullable|string|max:1000',
            'anggota' => 'nullable|array',
            'anggota.*.id_user' => 'nullable|integer|exists:users,id_user',
            'anggota.*.nama' => 'nullable|string|max:100',
            'anggota.*.peran' => 'nullable|string',
            'dokumen' => 'required|array|min:1',
            'dokumen.*.jenis' => 'required|string|max:100',
            'dokumen.*.tautan' => 'required|url:http,https|max:2048',
        ];
    }
}
