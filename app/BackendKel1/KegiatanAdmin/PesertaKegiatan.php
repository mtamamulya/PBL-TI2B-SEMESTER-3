<?php

namespace App\BackendKel1\KegiatanAdmin;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PesertaKegiatan
{
    public const TIPE = 'anggota_kegiatan';

    public const FORMAT = 'regex:/^(SemuaDosen|Angkatan:[0-9]{4}|(?:Dosen|Mahasiswa):[1-9][0-9]*)$/';

    public static function isian(string $jenis, array $field): bool
    {
        return in_array($jenis, KegiatanAdminSimpan::JENIS, true) && ($field['jenis_input'] === self::TIPE
            || ($field['jenis_input'] === 'teks' && strcasecmp(trim($field['input']), 'Anggota Kegiatan') === 0));
    }

    /** Mahasiswa aktif dengan penempatan terakhir di prodi yang dikelola aplikasi. */
    public static function mahasiswaAngkatan(): Builder
    {
        $penempatan = DB::table('enrollment_mahasiswa_tingkat_kelas as emt')
            ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emt.id_enrollment_tingkat_kelas')
            ->whereColumn('emt.id_mahasiswa', 'm.id_mahasiswa')
            ->orderByDesc('etk.id_tahun_akademik')
            ->orderByDesc('emt.id_enrollment_mahasiswa_tingkat_kelas')
            ->limit(1)
            ->select('etk.id_prodi');

        return DB::table('mahasiswa as m')
            ->join('users as u', 'u.id_user', '=', 'm.id_user')
            ->where('u.status', true)->where('u.id_role', 4)
            ->where('m.status_mahasiswa', 'Aktif')
            ->where($penempatan, '=', DB::table('prodi')->where('kode_prodi', config('admin.prodi.kode', 'TRK'))->select('id_prodi')->limit(1));
    }

    /** @return array{list<int>, string} */
    public static function pilih(string $jenis, array $pilihan, string $kolom): array
    {
        $semuaDosen = in_array('SemuaDosen', $pilihan, true);
        $grup = collect($pilihan)->reject(fn ($nilai) => $nilai === 'SemuaDosen')->unique()->groupBy(fn ($nilai) => explode(':', $nilai, 2)[0])
            ->map(fn ($baris) => $baris->map(fn ($nilai) => (int) explode(':', $nilai, 2)[1])->all());
        $tahun = $grup->get('Angkatan', []);
        if (($jenis === 'Kegiatan Dosen' && $tahun !== []) || ($jenis !== 'Kegiatan Dosen' && $semuaDosen)) {
            throw ValidationException::withMessages([$kolom => 'Pilihan kelompok peserta tidak sesuai dengan jenis kegiatan.']);
        }

        $peserta = [];
        if ($tahun !== []) {
            $angkatan = self::mahasiswaAngkatan()->whereIn('m.tahun_masuk', $tahun)->get(['m.tahun_masuk', 'm.id_user']);
            if (array_diff($tahun, $angkatan->pluck('tahun_masuk')->all()) !== []) {
                throw ValidationException::withMessages([$kolom => 'Angkatan yang dipilih sudah tidak memiliki mahasiswa aktif di prodi ini.']);
            }
            $peserta = $angkatan->pluck('id_user')->all();
        } elseif ($semuaDosen) {
            $peserta = self::pesertaAktif('dosen', 3)->pluck('users.id_user')->all();
            if ($peserta === []) {
                throw ValidationException::withMessages([$kolom => 'Tidak ada dosen aktif yang bisa ditambahkan.']);
            }
        }

        foreach (['Dosen' => 3, 'Mahasiswa' => 4] as $tipe => $role) {
            $id = $grup->get($tipe, []);
            if ($id === []) {
                continue;
            }
            $tabel = strtolower($tipe);
            $query = self::pesertaAktif($tabel, $role)->whereIn("$tabel.id_$tabel", $id);
            $idUser = $query->pluck('users.id_user')->all();
            if (count($idUser) !== count($id)) {
                throw ValidationException::withMessages([$kolom => 'Peserta yang dipilih sudah tidak aktif atau tidak ditemukan.']);
            }
            $peserta = array_merge($peserta, $idUser);
        }

        $peserta = array_values(array_unique(array_map('intval', $peserta)));
        sort($tahun);
        $kelompok = $semuaDosen ? ' (semua dosen aktif)' : ($tahun ? ' (angkatan '.implode(', ', $tahun).')' : '');

        return [$peserta, count($peserta).' peserta'.$kelompok];
    }

    private static function pesertaAktif(string $tabel, int $role): Builder
    {
        $query = DB::table($tabel)->join('users', 'users.id_user', '=', "$tabel.id_user")
            ->where('users.status', true)->where('users.id_role', $role);
        if ($tabel === 'mahasiswa') {
            $query->where('mahasiswa.status_mahasiswa', 'Aktif');
        }

        return $query;
    }
}
