<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data UJI kurikulum + mata kuliah + mahasiswa untuk menguji API Kelompok 1 dari Kelompok 2.
 * Semua baris bertanda UJI (nama '[UJI] ...', kode 'UJI...', NIM 'UJI...', email '@uji.trk.test')
 * sehingga aman dipasang berulang dan bisa dihapus bersih: php artisan uji:data-mk --hapus
 */
class UjiDataMk extends Command
{
    protected $signature = 'uji:data-mk {--hapus : Hapus semua data uji} {--tanpa-mahasiswa : Lewati akun mahasiswa uji} {--yes : Jangan minta konfirmasi}';

    protected $description = 'Pasang/hapus data uji kurikulum, mata kuliah, dan mahasiswa (bertanda [UJI])';

    private const PASSWORD = 'UjiTrk2026!';

    private const PENANDA = '[UJI]';

    /** nama kurikulum => [tahun_terbit, daftar semester yang diisi] */
    private const KURIKULUM = [
        '[UJI] Kurikulum 2019' => [2019, [1, 2]],
        '[UJI] Kurikulum 2021' => [2021, [1, 2, 3, 4, 5, 6, 7, 8]],
        '[UJI] Kurikulum 2024' => [2024, [1, 2, 3, 4, 5, 6, 7, 8]],
    ];

    /** semester => 5 nama mata kuliah */
    private const MK = [
        1 => ['Matematika Dasar', 'Algoritma dan Pemrograman', 'Dasar Elektronika', 'Bahasa Inggris', 'Pendidikan Pancasila'],
        2 => ['Basis Data', 'Pemrograman Berorientasi Objek', 'Rangkaian Digital', 'Statistika', 'Jaringan Komputer Dasar'],
        3 => ['Struktur Data', 'Pemrograman Web', 'Mikrokontroler', 'Sistem Operasi', 'Aljabar Linear'],
        4 => ['Pemrograman Mobile', 'Sistem Tertanam', 'Jaringan Komputer', 'Rekayasa Perangkat Lunak', 'Basis Data Lanjut'],
        5 => ['Internet of Things', 'Keamanan Jaringan', 'Kecerdasan Buatan', 'Interaksi Manusia dan Komputer', 'Metodologi Penelitian'],
        6 => ['Pengolahan Citra Digital', 'Komputasi Awan', 'Pemrograman Game', 'Manajemen Proyek TI', 'Praktik Kerja Lapangan'],
        7 => ['Machine Learning', 'Sistem Terdistribusi', 'Kewirausahaan', 'Etika Profesi', 'Proyek Rekayasa'],
        8 => ['Tugas Akhir', 'Seminar Proyek Akhir', 'Magang Industri', 'Etika Teknologi', 'Audit Sistem Informasi'],
    ];

    /** nim => [nama, tahun_masuk] */
    private const MAHASISWA = [
        'UJI2018001' => ['[UJI] Mahasiswa Angkatan 2018', 2018],
        'UJI2020001' => ['[UJI] Mahasiswa Angkatan 2020', 2020],
        'UJI2022001' => ['[UJI] Mahasiswa Angkatan 2022', 2022],
        'UJI2025001' => ['[UJI] Mahasiswa Angkatan 2025', 2025],
    ];

    public function handle(): int
    {
        $hapus = (bool) $this->option('hapus');
        $db = DB::getDatabaseName();

        if (! $this->option('yes')
            && ! $this->confirm(($hapus ? 'HAPUS' : 'TULIS').' data uji pada database "'.$db.'". Lanjut?', false)) {
            $this->warn('Dibatalkan.');

            return self::FAILURE;
        }

        return $hapus ? $this->hapus() : $this->pasang();
    }

    private function pasang(): int
    {
        $idProdi = DB::table('prodi')->orderBy('id_prodi')->value('id_prodi');
        if (! $idProdi) {
            $this->error('Tabel prodi masih kosong. Buat satu prodi dulu.');

            return self::FAILURE;
        }

        $dengan = ! $this->option('tanpa-mahasiswa');
        $idRole = $dengan ? DB::table('roles')->where('nama_role', 'mahasiswa')->value('id_role') : null;
        if ($dengan && ! $idRole) {
            $this->error('Role "mahasiswa" tidak ditemukan di tabel roles. Pakai --tanpa-mahasiswa atau buat role itu.');

            return self::FAILURE;
        }

        // Pakai file PDF kurikulum asli (jika ada) supaya halaman /kurikulum tidak error pada data uji.
        $linkAsli = DB::table('kurikulum')->where('nama_kurikulum', 'not like', self::PENANDA.'%')->whereNotNull('link')->value('link');
        $sekarang = now();

        DB::transaction(function () use ($idProdi, $linkAsli, $sekarang, $dengan, $idRole) {
            foreach (self::KURIKULUM as $nama => [$tahun, $semesterTerisi]) {
                $id = DB::table('kurikulum')->where('nama_kurikulum', $nama)->value('id_kurikulum');
                $isi = [
                    'id_prodi' => $idProdi, 'status' => 1, 'link' => $linkAsli, 'tahun_terbit' => $tahun,
                    'deskripsi' => '[UJI] Data uji untuk pengujian API. Hapus dengan: php artisan uji:data-mk --hapus',
                    'updated_at' => $sekarang,
                ];
                if ($id) {
                    DB::table('kurikulum')->where('id_kurikulum', $id)->update($isi);
                } else {
                    $id = DB::table('kurikulum')->insertGetId(['nama_kurikulum' => $nama, 'created_at' => $sekarang] + $isi);
                }

                foreach ($semesterTerisi as $sem) {
                    foreach (self::MK[$sem] as $i => $namaMk) {
                        $kode = sprintf('UJI%02d%d%02d', $tahun % 100, $sem, $i + 1);
                        $praktik = ($i % 3 === 0) ? 2 : 1;
                        $teori = ($i % 2 === 0) ? 2 : 3;
                        $data = [
                            'semester' => $sem,
                            'nama_mk' => "[UJI] {$namaMk} (K{$tahun})",
                            'sks_teori' => $teori, 'sks_praktik' => $praktik,
                            'jam_teori' => $teori, 'jam_praktek' => $praktik * 2,
                            'updated_at' => $sekarang,
                        ];
                        $ada = DB::table('mata_kuliah')->where('id_kurikulum', $id)->where('kode_mk', $kode)->exists();
                        if ($ada) {
                            DB::table('mata_kuliah')->where('id_kurikulum', $id)->where('kode_mk', $kode)->update($data);
                        } else {
                            DB::table('mata_kuliah')->insert($data + ['id_kurikulum' => $id, 'kode_mk' => $kode, 'created_at' => $sekarang]);
                        }
                    }
                }
            }

            if ($dengan) {
                foreach (self::MAHASISWA as $nim => [$nama, $tahun]) {
                    $email = strtolower($nim).'@uji.trk.test';
                    $idUser = DB::table('users')->where('email', $email)->value('id_user');
                    if (! $idUser) {
                        $idUser = DB::table('users')->insertGetId([
                            'id_role' => $idRole, 'email' => $email, 'password' => Hash::make(self::PASSWORD),
                            'status' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang,
                        ]);
                    }
                    $data = ['id_user' => $idUser, 'nama' => $nama, 'tahun_masuk' => $tahun, 'status_mahasiswa' => 'Aktif', 'updated_at' => $sekarang];
                    if (DB::table('mahasiswa')->where('nim', $nim)->exists()) {
                        DB::table('mahasiswa')->where('nim', $nim)->update($data);
                    } else {
                        DB::table('mahasiswa')->insert($data + ['nim' => $nim, 'created_at' => $sekarang]);
                    }
                }
            }
        });

        $this->ringkasan($dengan);

        return self::SUCCESS;
    }

    private function hapus(): int
    {
        $hasil = DB::transaction(function () {
            $idKurikulum = DB::table('kurikulum')->where('nama_kurikulum', 'like', self::PENANDA.'%')->pluck('id_kurikulum');
            $mk = DB::table('mata_kuliah')->whereIn('id_kurikulum', $idKurikulum)->delete();
            $kur = DB::table('kurikulum')->whereIn('id_kurikulum', $idKurikulum)->delete();

            $idUser = DB::table('mahasiswa')->where('nim', 'like', 'UJI%')->pluck('id_user');
            $mhs = DB::table('mahasiswa')->where('nim', 'like', 'UJI%')->delete();
            $usr = DB::table('users')->whereIn('id_user', $idUser)->where('email', 'like', '%@uji.trk.test')->delete();

            return compact('mk', 'kur', 'mhs', 'usr');
        });

        $this->info("Dihapus: {$hasil['kur']} kurikulum, {$hasil['mk']} mata kuliah, {$hasil['mhs']} mahasiswa, {$hasil['usr']} akun.");

        return self::SUCCESS;
    }

    private function ringkasan(bool $dengan): void
    {
        $this->info('Data uji terpasang.');
        $this->newLine();

        $baris = DB::table('kurikulum')->orderBy('id_kurikulum')->get()->map(fn ($k) => [
            $k->id_kurikulum, $k->nama_kurikulum, $k->tahun_terbit,
            DB::table('mata_kuliah')->where('id_kurikulum', $k->id_kurikulum)->count(),
        ])->all();
        $this->table(['id_kurikulum', 'nama_kurikulum', 'tahun_terbit', 'jumlah MK'], $baris);

        if ($dengan) {
            $this->table(['nim', 'email', 'tahun_masuk (angkatan)'], DB::table('mahasiswa')
                ->join('users', 'users.id_user', '=', 'mahasiswa.id_user')
                ->where('nim', 'like', 'UJI%')->orderBy('nim')
                ->get(['nim', 'email', 'tahun_masuk'])->map(fn ($m) => [$m->nim, $m->email, $m->tahun_masuk])->all());
            $this->line('Password semua akun mahasiswa uji: '.self::PASSWORD);
        }

        $this->newLine();
        $this->line('Hapus kapan saja dengan: php artisan uji:data-mk --hapus');
    }
}
