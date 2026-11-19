<?php

/*
 * DATA UJI Kegiatan (lokal saja). Semua data diberi penanda [UJI] / @uji.test.
 * Dosen/mahasiswa uji ikut dibuat (tabel dosen & mahasiswa; terhapus otomatis saat user dihapus).
 * Jalankan dari folder project API:
 *   php artisan tinker --execute="require base_path('uji-kegiatan.php');"
 * Aman dijalankan berulang (tidak membuat data ganda).
 */

use App\Models\IsianKategori;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 1. Role 1-4 (admin, kaprodi, dosen, mahasiswa): dibuat hanya bila tabel roles masih kosong.
if (DB::table('roles')->count() === 0) {
    $kolom = Schema::getColumnListing('roles');
    foreach ([1 => 'admin', 2 => 'kaprodi', 3 => 'dosen', 4 => 'mahasiswa'] as $id => $nama) {
        $baris = ['id_role' => $id];
        if (in_array('nama_role', $kolom, true)) {
            $baris['nama_role'] = $nama;
        }
        if (in_array('created_at', $kolom, true)) {
            $baris['created_at'] = $baris['updated_at'] = now();
        }
        DB::table('roles')->insert($baris);
    }
    echo "Role dibuat.\n";
}
foreach ([1, 2, 3, 4] as $r) {
    if (! DB::table('roles')->where('id_role', $r)->exists()) {
        echo "PERINGATAN: role dengan id_role={$r} tidak ada; user uji untuk role itu dilewati.\n";
    }
}

// 2. Jenis kegiatan + kategori + isian (isian "tanggal_mulai" dipakai API untuk tanggal & semester).
$jenis = [
    'dosen' => JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Dosen']),
    'mahasiswa' => JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Mahasiswa']),
    'prodi' => JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Prodi']),
];

$kategori = [];
foreach ([
    'dosen' => '[UJI] Seminar',
    'mahasiswa' => '[UJI] Lomba',
    'prodi' => '[UJI] Workshop',
] as $j => $nama) {
    $k = Kategori::firstOrCreate(
        ['id_jenis_kegiatan' => $jenis[$j]->getKey(), 'nama_kategori' => $nama],
        ['deskripsi' => 'Data uji', 'kebutuhan' => 'kegiatan'],
    );
    foreach ([['penyelenggara', 'teks'], ['tanggal_mulai', 'tanggal'], ['tanggal_selesai', 'tanggal']] as [$input, $tipe]) {
        if (! IsianKategori::where('id_kategori', $k->getKey())->where('input', $input)->exists()) {
            $k->isianKategori()->create(['input' => $input, 'jenis_input' => $tipe, 'aturan' => 'NULL', 'jumlah_isian' => 1]);
        }
    }
    $kategori[$j] = $k->load('isianKategori');
}

// 3. User uji (password: password). Kegiatan prodi: pengaju = email akun kaprodi.
$user = [];
foreach ([
    'prodi' => [2, 'uji.kaprodi@uji.test'],
    'dosen' => [3, 'uji.dosen@uji.test'],
    'mahasiswa' => [4, 'uji.mahasiswa@uji.test'],
] as $j => [$role, $email]) {
    if (DB::table('roles')->where('id_role', $role)->exists()) {
        $user[$j] = User::firstOrCreate(['email' => $email], ['id_role' => $role, 'password' => 'password', 'status' => true]);
    }
}

// 3b. Profil dosen & mahasiswa (tabel sendiri, terhubung lewat id_user) supaya nama pengaju dan tingkat terisi.
if (isset($user['dosen'])) {
    DB::table('dosen')->updateOrInsert(
        ['id_user' => $user['dosen']->getKey()],
        ['nip' => 'UJI000000000000001', 'nama' => '[UJI] Wiktasari', 'created_at' => now(), 'updated_at' => now()],
    );
}
if (isset($user['mahasiswa'])) {
    DB::table('mahasiswa')->updateOrInsert(
        ['id_user' => $user['mahasiswa']->getKey()],
        ['nim' => 'UJI00000001', 'nama' => '[UJI] Rizky Pratama', 'tahun_masuk' => 2024, 'created_at' => now(), 'updated_at' => now()],
    );
}

// 4. Kegiatan: [jenis, nama, tanggal_mulai, status akhir]
$daftar = [
    ['dosen', '[UJI] Seminar Nasional Kecerdasan Buatan', '2026-10-05', 'diterima'],
    ['mahasiswa', '[UJI] Hackathon Nasional Polines', '2026-03-20', 'diterima'],
    ['prodi', '[UJI] Workshop Kurikulum OBE', '2026-01-15', 'diterima'],
    ['dosen', '[UJI] Kegiatan Belum Disetujui', '2026-11-01', 'diajukan'],
];

foreach ($daftar as $i => [$j, $nama, $tgl, $status]) {
    if (! isset($user[$j]) || Kegiatan::where('nama_kegiatan', $nama)->exists()) {
        continue;
    }

    $k = Kegiatan::create([
        'id_kategori' => $kategori[$j]->getKey(),
        'id_user' => $user[$j]->getKey(),
        'nama_kegiatan' => $nama,
        'nomor_sk' => 'UJI/'.($i + 1).'/SK/2026',
        'tanggal_sk' => '2026-09-28',
    ]);

    $isian = [
        'penyelenggara' => 'Politeknik Negeri Semarang',
        'tanggal_mulai' => $tgl,
        'tanggal_selesai' => $tgl,
    ];
    foreach ($kategori[$j]->isianKategori as $ik) {
        if (isset($isian[$ik->input])) {
            DB::table('detail_kegiatan')->insert([
                'id_kegiatan' => $k->getKey(),
                'id_isian_kategori' => $ik->getKey(),
                'isian' => $isian[$ik->input],
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    // Timeline: diajukan dulu, lalu (bila disetujui) diterima. API membaca status TERAKHIR.
    Timeline::create(['id_kegiatan' => $k->getKey(), 'status' => 'diajukan', 'tanggal_status' => now()->subDays(10)]);
    if ($status === 'diterima') {
        Timeline::create(['id_kegiatan' => $k->getKey(), 'status' => 'diterima', 'tanggal_status' => now()->subDays(5)]);
    }
}

echo 'Selesai. Kegiatan [UJI]: '.Kegiatan::where('nama_kegiatan', 'like', '[UJI]%')->count()
    .' (yang tampil di daftar: 3).'."\n";
