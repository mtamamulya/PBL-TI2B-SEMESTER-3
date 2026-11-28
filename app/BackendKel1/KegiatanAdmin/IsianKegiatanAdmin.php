<?php

namespace App\BackendKel1\KegiatanAdmin;

use App\BackendKel1\KategoriKegiatan\PilihanBaku;
use App\Models\Kategori;
use App\Models\KelompokBidang;
use App\Models\Skim;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class IsianKegiatanAdmin
{
    public static function tanpaPadanan(array $fields): array
    {
        $penyelenggara = collect($fields)->firstWhere('input', 'penyelenggara');
        if (($penyelenggara['jenis_input'] ?? null) !== 'teks' || (int) $penyelenggara['jumlah_isian'] !== 1) {
            return $fields;
        }

        // Kedua nama tersimpan di sebagian kategori lama. Pertahankan ID lama untuk jawaban historis.
        return array_values(array_filter($fields, fn ($field) => ! (
            $field['input'] === 'organizer' && $field['jenis_input'] === 'teks'
            && ! $field['wajib'] && (int) $field['jumlah_isian'] === 1
        )));
    }

    /** Opsi untuk isian pilihan yang sudah ada sebelum tipe pilihan baku diperkenalkan. */
    public static function pilihanLama(string $nama, string $tipe): ?array
    {
        if ($tipe !== 'pilihan') {
            return null;
        }

        $opsi = match ($nama) {
            'skim' => Skim::query()->orderBy('nama_skim')->pluck('nama_skim'),
            'kelompok_bidang' => KelompokBidang::query()->orderBy('nama_kelompok_bidang')->pluck('nama_kelompok_bidang'),
            default => null,
        };

        return $opsi?->map(fn ($nilai) => ['nilai' => $nilai, 'label' => $nilai])->all();
    }

    /** Dipanggil di dalam transaksi simpan setelah kategori dikunci. */
    public static function validasi(Kategori $kategori, array $jawaban): array
    {
        $fields = $kategori->isianKategori()->orderBy('id_isian_kategori')->lockForUpdate()->get()
            ->map(fn ($field) => [
                'id' => $field->getKey(),
                'input' => $field->input,
                'jenis_input' => $field->jenis_input,
                'wajib' => strtoupper(trim((string) $field->aturan)) === 'NOT NULL',
                'jumlah_isian' => $field->jumlah_isian,
            ])->all();
        $fields = collect(self::tanpaPadanan($fields))->keyBy('id');

        $rules = [];
        $attributes = [];
        foreach ($jawaban as $id => $baris) {
            if (! $fields->has($id) || array_filter(array_keys($baris), fn ($key) => ! is_int($key) || $key < 0)) {
                throw ValidationException::withMessages(['detail' => 'Daftar isian kategori berubah atau tidak valid. Periksa kembali isian yang tersedia.']);
            }
            $jawaban[$id] = array_filter($baris, fn ($value) => $value !== null && $value !== '');
        }

        foreach ($fields as $id => $field) {
            $key = "detail.$id";
            $rules[$key] = [$field['wajib'] ? 'required' : 'sometimes', 'array'];
            if ($field['jumlah_isian'] > 0) {
                $rules[$key][] = 'max:'.$field['jumlah_isian'];
            }
            $rules["$key.*"] = ['required', ...self::aturanNilai($field)];
            $attributes[$key] = $attributes["$key.*"] = $field['input'];
        }

        Validator::make(['detail' => $jawaban], $rules, [], $attributes)->validate();

        $tanggal = $fields->where('jenis_input', 'tanggal')->keyBy('input');
        $awal = $tanggal['tanggal_mulai']['id'] ?? null;
        $akhir = $tanggal['tanggal_selesai']['id'] ?? null;
        if ($awal && $akhir && ! empty($jawaban[$awal]) && ! empty($jawaban[$akhir])
            && reset($jawaban[$akhir]) < reset($jawaban[$awal])) {
            throw ValidationException::withMessages(["detail.$akhir" => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
        }

        return [$fields, $jawaban];
    }

    private static function aturanNilai(array $field): array
    {
        $tipe = $field['jenis_input'];
        $opsi = PilihanBaku::nilai($tipe) ?? collect(self::pilihanLama($field['input'], $tipe))->pluck('nilai')->all();
        if ($opsi !== [] || $tipe === 'dosen' || ($tipe === 'pilihan' && in_array($field['input'], ['skim', 'kelompok_bidang'], true))) {
            return ['string', Rule::in($opsi)];
        }

        return match ($tipe) {
            'angka' => ['numeric'],
            'rupiah' => ['numeric', 'min:0'],
            'tanggal' => ['date_format:Y-m-d'],
            'berkas' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt', 'max:5120'],
            default => ['string', 'max:10000'],
        };
    }
}
