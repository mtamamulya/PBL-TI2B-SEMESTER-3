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
 * Pemilik yang dipilih menjadi ketua. Jika kosong, id_user menyimpan akun pencatat.
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
    public static function aturan(bool $dinamis = false): array
    {
        return [
            'nama_kegiatan' => ['required', 'string', 'max:255'],
        ] + ($dinamis ? [
            'id_kategori' => ['required', 'integer'],
            'pemilik' => ['nullable', 'string', 'regex:/^(dosen|mahasiswa):\d+$/i'],
            'anggota' => ['prohibited'],
            'dokumen' => ['prohibited'],
            'detail' => ['sometimes', 'array'],
            'detail.*' => ['array'],
        ] : [
            'anggota' => ['required', 'array', 'min:1'],
            'anggota.*' => ['required', 'string', 'regex:/^(dosen|mahasiswa):\d+$/i'],
            'dokumen' => ['nullable', 'array'],
            'dokumen.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt', 'max:5120'],
            'dokumen.*.nama' => ['nullable', 'string', 'max:150'],
            'dokumen.*.jenis' => ['nullable', 'string', 'max:100'],
            'dokumen.*.id_jenis_dokumen' => ['nullable', 'integer'],
            'dokumen.*.keterangan' => ['nullable', 'string', 'max:500'],
            'dokumen.*.tautan' => ['nullable', 'url', 'max:255'],
            'jenis_kegiatan' => ['required', 'in:'.implode(',', self::JENIS)],
            'id_kategori' => ['nullable', 'integer', 'required_without:kategori'],
            'kategori' => ['nullable', 'string', 'max:100', 'required_without:id_kategori'],
            'penyelenggara' => ['required', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);
    }

    /** @return array{kegiatan: Kegiatan, peringatan: string[]} */
    public function simpan(array $d, User $pembuat, bool $dinamis = false): array
    {
        $tersimpan = [];
        $peringatan = [];

        try {
            $kegiatan = DB::transaction(function () use ($d, $pembuat, $dinamis, &$tersimpan, &$peringatan) {
                $kategori = $this->kategori($d, $dinamis);
                $jenis = $dinamis ? $kategori->jenisKegiatan?->nama_jenis_kegiatan : $d['jenis_kegiatan'];
                if ($dinamis && ($kategori->kebutuhan !== 'kegiatan' || ! in_array($jenis, self::JENIS, true))) {
                    throw ValidationException::withMessages(['id_kategori' => 'Pilih kategori yang tersedia untuk Langsung kegiatan.']);
                }
                if ($dinamis) {
                    $ketua = $this->pemilikDariForm($jenis, $d['pemilik'] ?? null);
                    $pemilik = $ketua ?? (int) $pembuat->getKey();
                    $dokumen = [];
                    [$fields, $jawaban, $peserta] = IsianKegiatanAdmin::validasi($kategori, $d['detail'] ?? []);
                    $anggota = $ketua === null ? $peserta : array_values(array_unique([$ketua, ...$peserta]));
                } else {
                    $anggota = $this->anggota($d['anggota']);
                    $pemilik = $this->pemilik($jenis, $anggota, $pembuat);
                    $ketua = $pemilik;
                    $dokumen = $this->dokumen($d['dokumen'] ?? []);
                }
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

                foreach ($dinamis ? [] : self::ISIAN_FORM as $input) {
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

                if ($dinamis) {
                    foreach ($jawaban as $idIsian => $baris) {
                        foreach ($baris as $nilai) {
                            if ($nilai instanceof UploadedFile) {
                                $nilai = $nilai->store('lampiran-isian', 'public');
                                if ($nilai === false) {
                                    throw ValidationException::withMessages(["detail.$idIsian" => 'Berkas tidak dapat disimpan. Coba unggah lagi.']);
                                }
                                $tersimpan[] = $nilai;
                            }
                            $detail = [
                                'id_kegiatan' => $k->id_kegiatan,
                                'id_isian_kategori' => $idIsian,
                                'isian' => (string) $nilai,
                            ];
                            if ($fields[$idIsian]['jumlah_isian'] === 0) {
                                // Master memakai 0 untuk isian berulang; model umum memperlakukan 0 sebagai batas literal.
                                DB::table('detail_kegiatan')->insert($detail + ['created_at' => now(), 'updated_at' => now()]);
                            } else {
                                DetailKegiatan::query()->create($detail);
                            }
                        }
                    }
                }

                foreach ($anggota as $idUser) {
                    AnggotaKegiatan::query()->create([
                        'id_kegiatan' => $k->id_kegiatan,
                        'id_user' => $idUser,
                        'peran' => $idUser === $ketua ? 'ketua' : 'anggota',
                    ]);
                }

                foreach ($dokumen as $indeks => $dok) {
                    $link = $dok['tautan'];
                    $tipe = 'tautan';
                    if ($dok['file'] instanceof UploadedFile) {
                        $link = $dok['file']->store('kegiatan', 'public');
                        if ($link === false) {
                            throw ValidationException::withMessages(["dokumen.$indeks.file" => 'Berkas tidak dapat disimpan. Coba unggah lagi.']);
                        }
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

    private function kategori(array $d, bool $dinamis): Kategori
    {
        $k = ! empty($d['id_kategori'])
            ? Kategori::query()->lockForUpdate()->find((int) $d['id_kategori'])
            : Kategori::query()->where('nama_kategori', $d['kategori'])->orderBy('id_kategori')->lockForUpdate()->first();

        if (! $k) {
            throw ValidationException::withMessages([$dinamis ? 'id_kategori' : 'kategori' => 'Kategori tidak ditemukan.']);
        }

        return $k;
    }

    /**
     * "Dosen:5" / "Mahasiswa:12" (id tabel dosen/mahasiswa) -> daftar id_user unik, urutan sama.
     *
     * @return int[]
     */
    private function anggota(array $daftar, string $kolom = 'anggota'): array
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
            throw ValidationException::withMessages([$kolom => $galat]);
        }

        return array_values(array_unique($users));
    }

    private function pemilikDariForm(string $jenis, ?string $kandidat): ?int
    {
        if ($jenis === 'Kegiatan Prodi' || $kandidat === null || $kandidat === '') {
            return null;
        }

        $tipe = $jenis === 'Kegiatan Dosen' ? 'dosen' : 'mahasiswa';
        if (! str_starts_with(strtolower($kandidat), $tipe.':')) {
            throw ValidationException::withMessages(['pemilik' => "Pilih $tipe sebagai pemilik kegiatan."]);
        }

        $pemilik = $this->anggota([$kandidat], 'pemilik')[0];
        if (! User::query()->whereKey($pemilik)->where('id_role', $tipe === 'dosen' ? 3 : 4)->exists()) {
            throw ValidationException::withMessages(['pemilik' => 'Pemilik tidak sesuai dengan jenis kegiatan.']);
        }

        return $pemilik;
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
