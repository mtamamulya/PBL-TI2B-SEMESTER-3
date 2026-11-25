<?php

namespace Database\Seeders;

use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class KegiatanDokumentasiSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->first();
        if (! $user) {
            $this->command?->warn('Tidak ada data user. Jalankan DatabaseSeeder terlebih dahulu.');

            return;
        }

        $jenisKegiatan = JenisKegiatan::firstOrCreate([
            'nama_jenis_kegiatan' => 'Kegiatan Mahasiswa',
        ]);

        $kategori = Kategori::firstOrCreate(
            ['nama_kategori' => 'Lomba'],
            ['id_jenis_kegiatan' => $jenisKegiatan->id_jenis_kegiatan]
        );

        $jenisFoto = JenisDokumen::updateOrCreate(
            ['nama_jenis_dokumen' => 'Foto Kegiatan'],
            ['keterangan' => 'Dokumentasi foto pelaksanaan kegiatan']
        );

        $daftarKegiatan = [
            [
                'nama_kegiatan' => 'Turnamen Debugging Sambil Merem & Minum Kopi Sachet',
                'nomor_sk' => 'SK/KOPI/001/2026',
                'tanggal_sk' => '2026-10-12',
                'foto' => [
                    ['file' => 'debug-merem.png', 'nama' => 'Peserta Ngetik Pakai Feeling.png', 'warna' => [34, 119, 255]],
                    ['file' => 'gelas-kopi-tinggi.png', 'nama' => 'Menara Gelas Kopi Jam 3 Pagi.png', 'warna' => [120, 53, 15]],
                ],
            ],
            [
                'nama_kegiatan' => 'Kunjungan Industri ke Server Warnet Angker 2008',
                'nomor_sk' => 'SK/WARNET/002/2026',
                'tanggal_sk' => '2026-10-20',
                'foto' => [
                    ['file' => 'server-kipas-angin.png', 'nama' => 'Pendingin Server Pakai Kipas Miyako.png', 'warna' => [16, 185, 129]],
                    ['file' => 'kabel-mie-instan.png', 'nama' => 'Topologi Kabel Mirip Mie Goreng.png', 'warna' => [245, 158, 11]],
                    ['file' => 'billing-explorer.png', 'nama' => 'Penampakan Billing Explorer Legendaris.png', 'warna' => [7, 30, 56]],
                ],
            ],
            [
                'nama_kegiatan' => 'Seminar Nasional Cara Menjelaskan Error 500 ke Dosen',
                'nomor_sk' => 'SK/PASRAH/003/2026',
                'tanggal_sk' => '2026-10-28',
                'foto' => [
                    ['file' => 'slide-senyum-manis.png', 'nama' => 'Slide 1 Tadi di Laptop Saya Bisa Pak.png', 'warna' => [239, 68, 68]],
                    ['file' => 'doa-bersama-demo.png', 'nama' => 'Doa Bersama Sebelum Live Demo.png', 'warna' => [139, 92, 246]],
                ],
            ],
            [
                'nama_kegiatan' => 'Lomba Kabel LAN Terkusut Tingkat RT/RW',
                'nomor_sk' => 'SK/RJ45/004/2026',
                'tanggal_sk' => '2026-11-03',
                'foto' => [
                    ['file' => 'crimping-kebalik.png', 'nama' => 'Urutan Warna Kabel Suka-Suka Hati.png', 'warna' => [236, 72, 153]],
                    ['file' => 'tes-lan-meledak.png', 'nama' => 'Ekspresi Pas LAN Tester Lampunya Lompat.png', 'warna' => [20, 184, 166]],
                ],
            ],
            [
                'nama_kegiatan' => 'Workshop Menenangkan Laptop yang Suaranya Mirip Pesawat Jet',
                'nomor_sk' => 'SK/THERMAL/005/2026',
                'tanggal_sk' => '2026-11-10',
                'foto' => [
                    ['file' => 'kipas-takeoff.png', 'nama' => 'Laptop Siap Lepas Landas Pas Buka Chrome.png', 'warna' => [59, 130, 246]],
                    ['file' => 'pasta-gigi-thermal.png', 'nama' => 'Mitos Oles Thermal Paste Pakai Pepsodent.png', 'warna' => [107, 114, 128]],
                ],
            ],
            [
                'nama_kegiatan' => 'Ekspedisi Mencari Titik Koma yang Hilang di Jam 2 Pagi',
                'nomor_sk' => 'SK/SEMICOLON/006/2026',
                'tanggal_sk' => '2026-11-18',
                'foto' => [
                    ['file' => 'titik-koma-ketemu.png', 'nama' => 'Momen Haru Ketemu Kurung Kurawal Kurang Satu.png', 'warna' => [34, 197, 94]],
                    ['file' => 'tidur-di-keyboard.png', 'nama' => 'Jejak Tombol QWERTY di Pipi Kiri.png', 'warna' => [249, 115, 22]],
                ],
            ],
        ];

        foreach ($daftarKegiatan as $item) {
            $kegiatan = Kegiatan::firstOrCreate(
                ['nama_kegiatan' => $item['nama_kegiatan']],
                [
                    'id_kategori' => $kategori->id_kategori,
                    'id_user' => $user->id_user,
                    'nomor_sk' => $item['nomor_sk'],
                    'tanggal_sk' => $item['tanggal_sk'],
                ]
            );

            foreach ($item['foto'] as $f) {
                $path = 'kegiatan/dokumentasi/'.$f['file'];
                $this->buatGambarContoh($path, $f['warna']);

                Dokumen::firstOrCreate(
                    [
                        'id_kegiatan' => $kegiatan->id_kegiatan,
                        'link_dokumen' => $path,
                    ],
                    [
                        'id_jenis_dokumen' => $jenisFoto->id_jenis_dokumen,
                        'nama_file' => $f['nama'],
                        'tipe_dokumen' => 'image/png',
                        'is_lampiran' => true,
                    ]
                );
            }
        }

        $this->command?->info('KegiatanDokumentasiSeeder berhasil dijalankan (6 kegiatan ngawur + 13 foto dokumentasi).');
    }

    private function buatGambarContoh(string $path, array $rgb): void
    {
        if (Storage::disk('public')->exists($path)) {
            return;
        }

        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(800, 450);
            $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            imagefill($img, 0, 0, $bg);
            ob_start();
            imagepng($img);
            $binary = ob_get_clean();
            imagedestroy($img);

            Storage::disk('public')->put($path, (string) $binary);

            return;
        }

        $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        Storage::disk('public')->put($path, $png1x1);
    }
}
