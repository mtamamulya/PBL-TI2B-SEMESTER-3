<?php

namespace Database\Seeders;

use App\Models\Berita;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BeritaSeeder extends Seeder
{
    public function run(): void
    {
        $userId = DB::table('users')->value('id_user');

        if (! $userId) {
            $this->command?->warn('Tabel users masih kosong. Buat minimal 1 akun user terlebih dahulu sebelum menjalankan BeritaSeeder.');

            return;
        }

        $hasKategori = Schema::hasColumn('berita', 'id_kategori');
        $kategoriMap = Schema::hasTable('kategori')
            ? DB::table('kategori')->pluck('id_kategori', 'nama_kategori')
            : collect();

        $daftarBerita = [
            [
                'judul_berita' => 'Jaringan Auto-Crafting AE2 di Star Technology Akhirnya Bebas Bottleneck Setelah 14 Jam Begadang',
                'kategori' => 'Info Akademik',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(1),
                'isi_berita' => implode("\n\n", [
                    'Setelah melalui drama kekurangan channel kabel ME dan tumpukan resep crafting yang saling bertabrakan, infrastruktur Applied Energistics 2 (AE2) di modpack Star Technology akhirnya berhasil beroperasi penuh tanpa macet.',
                    'Perbaikan utama dilakukan dengan merombak jalur P2P Tunnel serta memisahkan blok Molecular Assembler dan Pattern Provider ke dalam sub-jaringan khusus pemrosesan bijih dan komponen sirkuit.',
                    'Kini seluruh permintaan komponen mesin tingkat lanjut dapat diproduksi secara otomatis hanya dengan satu kali klik, tanpa perlu lagi menyusun bahan mentah secara manual di meja kerja.',
                ]),
            ],
            [
                'judul_berita' => 'Mahasiswa TRK Berhasil Melatih AI untuk Mendeteksi Mood Dosen dari Cara Mengetik Spasi',
                'kategori' => 'Prestasi Mahasiswa',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(2),
                'isi_berita' => implode("\n\n", [
                    'Tim riset gabungan mahasiswa Teknologi Rekayasa Komputer mengklaim telah menemukan terobosan baru dalam bidang pemrosesan sinyal akustik berbasis kecerdasan buatan yang diberi nama SpasiSense v1.0.',
                    'Sistem ini menganalisis frekuensi ketukan tombol spasi pada keyboard mekanikal dan membandingkannya dengan kecepatan kursor saat presentasi berlangsung. Akurasinya diklaim mencapai 99,4 persen dalam memprediksi apakah sesi tanya jawab akan berjalan damai atau penuh pertanyaan jebakan.',
                    'Meski belum diuji secara klinis, prototipe ini sudah menjadi acuan utama mahasiswa barisan depan untuk menentukan kapan waktu terbaik mengajukan izin ke toilet.',
                ]),
            ],
            [
                'judul_berita' => 'Reaktor Nuklir dan Closed-Loop Chemical Processing Star Technology Sukses Beroperasi Stabil',
                'kategori' => 'Lomba',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(3),
                'isi_berita' => implode("\n\n", [
                    'Proyek pembangunan pembangkit listrik tenaga nuklir dan rantai pemrosesan kimia tertutup (closed-loop chemical processing) di base utama Star Technology resmi dinyatakan lolos uji beban penuh.',
                    'Seluruh residu reaksi kimia seperti asam sulfat, klorin, dan hidrogen kini didaur ulang secara otomatis menggunakan jaringan pipa fluida bertekanan tinggi untuk memasok lini produksi Polytetrafluoroethylene (PTFE) dan pengolahan logam titanium.',
                    'Dengan surplus daya yang sangat besar, seluruh mesin multiblock GregTech dapat berjalan bersamaan pada overclock maksimal tanpa risiko kabel terbakar maupun ledakan reaktor di tengah malam.',
                ]),
            ],
            [
                'judul_berita' => 'Tragedi Lab IoT: Lupa Pasang Heatsink pada ESP32, Inkubator Telur Malah Sukses Bikin Telur Ceplok',
                'kategori' => 'Info Akademik',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(4),
                'isi_berita' => implode("\n\n", [
                    'Uji coba prototipe sistem pemantauan inkubator pintar berbasis ESP32 dan sensor suhu DS18B20 mengalami belokan tak terduga akibat kesalahan kalibrasi nilai aturan pada kontroler Fuzzy Logic.',
                    'Alih-alih menjaga suhu ruang penetasan tetap stabil di angka 37,5 derajat Celsius, elemen pemanas justru bekerja pada siklus penuh selama dua puluh menit tanpa henti karena sensor pembacaan sempat terlepas dari dudukannya.',
                    'Beruntung tidak ada komponen yang rusak, dan tim pengembang memutuskan hasil uji coba tersebut tetap layak dikonsumsi sebagai sarapan pagi sebelum kelas praktikum dimulai.',
                ]),
            ],
            [
                'judul_berita' => 'Ditemukan Spesies Baru di Ruang Lab Jaringan: Kabel LAN Cat6 yang Bisa Melilit Sendiri',
                'kategori' => 'Kunjungan Industri',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(5),
                'isi_berita' => implode("\n\n", [
                    'Fenomena misterius kembali terjadi di meja praktikum jaringan komputer. Dua puluh kabel UTP Cat6 yang sebelumnya sudah digulung rapi menggunakan velcro ditemukan kembali membentuk simpul mati hanya dalam waktu 45 menit saat ditinggal makan siang.',
                    'Para peneliti menduga simpul tersebut terbentuk akibat kombinasi medan magnet dari router Cisco yang sedang menjalankan protokol OSPF dan hukum entropi ruang laboratorium yang tidak pernah beres.',
                    'Sebagai langkah mitigasi, seluruh mahasiswa diwajibkan memberi label nama pada masing-masing ujung kabel agar tidak tertukar dengan kabel milik kelompok sebelah.',
                ]),
            ],
            [
                'judul_berita' => 'Krisis Sirkuit HV dan Plat Baja Star Technology Teratasi Berkat Lini Electric Blast Furnace Terpadu',
                'kategori' => 'Magang',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(6),
                'isi_berita' => implode("\n\n", [
                    'Kelangkaan stok ingot baja dan komponen sirkuit tegangan tinggi (HV) yang sempat menghambat ekspansi pabrik di Star Technology akhirnya berhasil diatasi setelah penambahan tiga unit Electric Blast Furnace (EBF) paralel.',
                    'Setiap tungku peleburan dilengkapi dengan input bus terpisah dan sistem suplai oksigen otomatis sehingga waktu peleburan berkurang hingga setengahnya.',
                    'Langkah berikutnya adalah memperluas kapasitas penyimpanan digital AE2 agar mampu menampung jutaan unit bahan baku tambang untuk persiapan menuju tier teknologi berikutnya.',
                ]),
            ],
            [
                'judul_berita' => 'Draf Rahasia: Rencana Pembangkit Listrik Kampus Tenaga Kinetik Mahasiswa Lari Mengejar Absen',
                'kategori' => 'Info Akademik',
                'status' => 'draft',
                'tanggal_publish' => null,
                'isi_berita' => implode("\n\n", [
                    'Dokumen konsep awal pemanfaatan ubin piezoelektrik di koridor gedung perkuliahan untuk memanen energi kinetik dari langkah kaki mahasiswa yang berlari pada pukul 07.29 WIB.',
                    'Artikel ini masih berstatus draf internal sehingga tidak boleh tampil pada halaman publik landing page.',
                ]),
            ],
        ];

        foreach ($daftarBerita as $item) {
            $payload = [
                'id_user' => $userId,
                'id_kegiatan' => null,
                'isi_berita' => $item['isi_berita'],
                'status' => $item['status'],
                'tanggal_publish' => $item['tanggal_publish'],
                'sampul' => null,
                'catatan_revisi' => null,
            ];

            if ($hasKategori) {
                $payload['id_kategori'] = $kategoriMap[$item['kategori']] ?? null;
            }

            Berita::query()->updateOrCreate(
                ['judul_berita' => $item['judul_berita']],
                $payload
            );
        }

        $this->command?->info('BeritaSeeder berhasil dijalankan (6 berita publish, 1 berita draft).');
    }
}
