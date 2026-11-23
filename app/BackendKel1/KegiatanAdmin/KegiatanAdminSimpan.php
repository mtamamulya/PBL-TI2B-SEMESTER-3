<?php

namespace App\BackendKel1\KegiatanAdmin;

use App\Models\AnggotaKegiatan;
use App\Models\DetailKegiatan;
use App\Models\Dokumen;
use App\Models\IsianKategori;
use App\Models\JenisDokumen;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Menyimpan kegiatan baru dari admin/kaprodi: langsung berstatus 'diterima' (tanpa proses pengajuan).
 *
 * Pemilik kegiatan (kegiatan.id_user) menentukan tab:
 *   Kegiatan Prodi     -> akun admin/kaprodi yang membuat (pengaju = email akun itu)
 *   Kegiatan Dosen     -> dosen pertama pada daftar anggota (peran ketua)
 *   Kegiatan Mahasiswa -> mahasiswa pertama pada daftar anggota (peran ketua)
 */
class KegiatanAdminSimpan
{
    public const JENIS = ['Kegiatan Dosen', 'Kegiatan Mahasiswa', 'Kegiatan Prodi'];

    /** Isian kategori yang diisi dari form; yang tidak ada pada kategorinya dilaporkan sebagai peringatan. */
    private const ISIAN_FORM = ['penyelenggara', 'tanggal_mulai', 'tanggal_selesai'];

    /** Kunci berbentuk "dokumen[0][nama]" (dari multipart / ApiClient) diubah menjadi larik bersarang. */
    public static function normalisasi(array $masuk): array
    {
        $hasil = [];
        foreach ($masuk as $kunci => $nilai) {
            if (is_string($kunci) && str_contains($kunci, '[')) {
                Arr::set($hasil, str_replace(['][', '[', ']'], ['.', '.', ''], $kunci), $nilai);
            } else {
                $hasil[$kunci] = $nilai;
            }
        }

        return $hasil;
    }

    /** @return array<string, array<int, mixed>> */
    public static function aturan(): array
    {
        return [
            'jenis_kegiatan' => ['required', 'in:'.implode(',', self::JENIS)],
            'id_kategori' => ['nullable', 'integer', 'required_without:kategori'],
            'kategori' => ['nullable', 'string', 'max:100', 'required_without:id_kategori'],
            'nama_kegiatan' => ['required', 'string', 'max:255'],
            'penyelenggara' => ['required', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'anggota' => ['required', 'array', 'min:1'],
            'anggota.*' => ['required', 'string', 'regex:/^(dosen|mahasiswa):\d+$/i'],
            'dokumen' => ['nullable', 'array'],
            'dokumen.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt', 'max:5120'],
            'dokumen.*.nama' => ['nullable', 'string', 'max:150'],
            'dokumen.*.jenis' => ['nullable', 'string', 'max:100'],
            'dokumen.*.id_jenis_dokumen' => ['nullable', 'integer'],
            'dokumen.*.keterangan' => ['nullable', 'string', 'max:500'],
            'dokumen.*.tautan' => ['nullable', 'url', 'max:255'],
        ];
    }

    /** @return array{kegiatan: Kegiatan, peringatan: string[]} */
    public function simpan(array $d, User $pembuat): array
    {
        $kategori = $this->kategori($d);
        $anggota = $this->anggota((array) $d['anggota']);
        $pemilik = $this->pemilik($d['jenis_kegiatan'], $anggota, $pembuat);
        $dokumen = $this->dokumen((array) ($d['dokumen'] ?? []));

        $tersimpan = [];
        $peringatan = [];

        try {
            $kegiatan = DB::transaction(function () use ($d, $kategori, $anggota, $pemilik, $dokumen, &$tersimpan, &$peringatan) {
                $k = Kegiatan::query()->create([
                    'id_kategori' => $kategori->id_kategori,
                    'id_user' => $pemilik,
                    'nama_kegiatan' => $d['nama_kegiatan'],
                ]);

                Timeline::query()->create([
                    'id_kegiatan' => $k->id_kegiatan,
                    'status' => 'diterima',
                    'tanggal_status' => now(),
                ]);

                foreach (self::ISIAN_FORM as $input) {
                    $isian = IsianKategori::query()
                        ->where('id_kategori', $kategori->id_kategori)->where('input', $input)->first();
                    if (! $isian) {
                        $peringatan[] = "Kategori \"{$kategori->nama_kategori}\" belum punya isian \"{$input}\", nilainya tidak disimpan.";

                        continue;
                    }
                    DetailKegiatan::query()->create([
                        'id_kegiatan' => $k->id_kegiatan,
                        'id_isian_kategori' => $isian->id_isian_kategori,
                        'isian' => $d[$input],
                    ]);
                }

                foreach ($anggota as $idUser) {
                    AnggotaKegiatan::query()->create([
                        'id_kegiatan' => $k->id_kegiatan,
                        'id_user' => $idUser,
                        'peran' => $idUser === $pemilik ? 'ketua' : 'anggota',
                    ]);
                }

                foreach ($dokumen as $dok) {
                    $link = $dok['tautan'];
                    $tipe = 'tautan';
                    if ($dok['file'] instanceof UploadedFile) {
                        $link = $dok['file']->store('kegiatan', 'public');
                        $tersimpan[] = $link;
                        $tipe = strtolower($dok['file']->getClientOriginalExtension());
                    }
                    Dokumen::query()->create([
                        'id_jenis_dokumen' => $dok['id_jenis'],
                        'id_kegiatan' => $k->id_kegiatan,
                        'nama_file' => $dok['nama'],
                        'tipe_dokumen' => $tipe,
                        'link_dokumen' => $link,
                        'is_lampiran' => false,
                    ]);
                }

                return $k;
            });
        } catch (Throwable $e) {
            foreach ($tersimpan as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return ['kegiatan' => $kegiatan, 'peringatan' => $peringatan];
    }

    private function kategori(array $d): Kategori
    {
        $k = ! empty($d['id_kategori'])
            ? Kategori::query()->find((int) $d['id_kategori'])
            : Kategori::query()->where('nama_kategori', $d['kategori'])->orderBy('id_kategori')->first();

        if (! $k) {
            throw ValidationException::withMessages(['kategori' => 'Kategori tidak ditemukan.']);
        }

        return $k;
    }

    /**
     * "Dosen:5" / "Mahasiswa:12" (id tabel dosen/mahasiswa) -> daftar id_user unik, urutan sama.
     *
     * @return int[]
     */
    private function anggota(array $daftar): array
    {
        $users = [];
        $galat = [];
        foreach ($daftar as $teks) {
            [$tipe, $id] = explode(':', strtolower((string) $teks));
            $idUser = $tipe === 'dosen'
                ? DB::table('dosen')->where('id_dosen', (int) $id)->value('id_user')
                : DB::table('mahasiswa')->where('id_mahasiswa', (int) $id)->value('id_user');
            if (! $idUser) {
                $galat[] = "Anggota {$teks} tidak ditemukan.";

                continue;
            }
            $users[] = (int) $idUser;
        }
        if ($galat) {
            throw ValidationException::withMessages(['anggota' => $galat]);
        }

        return array_values(array_unique($users));
    }

    /** @param  int[]  $anggota */
    private function pemilik(string $jenis, array $anggota, User $pembuat): int
    {
        if ($jenis === 'Kegiatan Prodi') {
            return (int) $pembuat->id_user;
        }

        $role = $jenis === 'Kegiatan Dosen' ? 3 : 4;
        $cocok = User::query()->whereIn('id_user', $anggota)->where('id_role', $role)->pluck('id_user')->all();
        // pemilik = anggota pertama (urutan form) yang berperan sesuai jenis kegiatan
        foreach ($anggota as $idUser) {
            if (in_array($idUser, $cocok, true)) {
                return (int) $idUser;
            }
        }

        throw ValidationException::withMessages([
            'anggota' => $jenis === 'Kegiatan Dosen'
                ? 'Kegiatan dosen harus memiliki minimal satu anggota dosen.'
                : 'Kegiatan mahasiswa harus memiliki minimal satu anggota mahasiswa.',
        ]);
    }

    /**
     * Baris dokumen kosong dilewati. Tiap baris harus punya file atau tautan, serta jenis dokumen yang dikenal.
     *
     * @return array<int, array{nama: string, file: ?UploadedFile, tautan: ?string, id_jenis: int}>
     */
    private function dokumen(array $daftar): array
    {
        $hasil = [];
        foreach ($daftar as $n => $row) {
            $file = $row['file'] ?? null;
            $tautan = $row['tautan'] ?? null;
            if (! $file && ! $tautan && empty($row['nama'])) {
                continue;
            }
            if (! $file && ! $tautan) {
                throw ValidationException::withMessages(["dokumen.$n.file" => 'Dokumen harus berisi file atau tautan.']);
            }

            $jenis = ! empty($row['id_jenis_dokumen'])
                ? JenisDokumen::query()->find((int) $row['id_jenis_dokumen'])
                : (! empty($row['jenis']) ? JenisDokumen::query()->where('nama_jenis_dokumen', $row['jenis'])->first() : null);
            if (! $jenis) {
                throw ValidationException::withMessages(["dokumen.$n.jenis" => 'Jenis dokumen tidak dikenal.']);
            }

            $hasil[] = [
                'nama' => (string) ($row['nama'] ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : $tautan)),
                'file' => $file instanceof UploadedFile ? $file : null,
                'tautan' => $file instanceof UploadedFile ? null : $tautan,
                'id_jenis' => (int) $jenis->id_jenis_dokumen,
            ];
        }

        return $hasil;
    }
}
