<?php

namespace App\BackendKel3\PrestasiBerita;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = 'required'; // Update menyimpan satu formulir lengkap, sama seperti kontrak frontend.

        return [
            'sumber' => ["{$required}", Rule::in(['kegiatan', 'mandiri'])],
            'id_kegiatan' => ['nullable', 'required_if:sumber,kegiatan', 'integer', 'exists:kegiatan,id_kegiatan'],
            'nama_prestasi' => ["{$required}", 'string', 'max:255'],
            'penyelenggara' => ["{$required}", 'string', 'max:150'],
            'tingkat' => ["{$required}", Rule::in(['kabupaten/kota', 'provinsi', 'nasional', 'internasional'])],
            'jenis' => ["{$required}", Rule::in(['akademik', 'non-akademik'])],
            'tanggal_mulai' => ["{$required}", 'date'],
            'tanggal_selesai' => ["{$required}", 'date', 'after_or_equal:tanggal_mulai'],
            'capaian' => ["{$required}", 'string', 'max:100'],
            'kategori' => ['nullable', 'string', 'max:150'],
            'bentuk' => ["{$required}", Rule::in(['individu', 'tim'])],
            'lokasi' => ['nullable', 'string', 'max:150'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'anggota' => ['nullable', 'array'],
            'anggota.*.id_user' => ['nullable', 'integer', 'exists:users,id_user'],
            'anggota.*.nama' => ['nullable', 'string', 'max:100'],
            'anggota.*.peran' => ['nullable', 'string'],
            'dokumen' => ["{$required}", 'array', 'min:1'],
            'dokumen.*.jenis' => ["{$required}", 'string', 'max:100'],
            'dokumen.*.tautan' => ["{$required}", 'url:http,https', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'sumber.required' => 'Sumber prestasi wajib dipilih.',
            'nama_prestasi.required' => 'Nama prestasi wajib diisi.',
            'penyelenggara.required' => 'Penyelenggara wajib diisi.',
            'tingkat.required' => 'Tingkat kejuaraan wajib dipilih.',
            'jenis.required' => 'Jenis prestasi wajib dipilih.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'capaian.required' => 'Capaian wajib diisi.',
            'bentuk.required' => 'Bentuk partisipasi wajib dipilih.',
            'dokumen.required' => 'Minimal 1 bukti dokumen wajib dilampirkan.',
            'dokumen.min' => 'Minimal 1 bukti dokumen wajib dilampirkan.',
            'dokumen.*.tautan.url' => 'Tautan dokumen harus URL valid (http/https).',
        ];
    }
}
