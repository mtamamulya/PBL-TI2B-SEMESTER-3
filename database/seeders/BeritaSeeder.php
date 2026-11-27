<?php

namespace Database\Seeders;

use App\Models\Berita;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class BeritaSeeder extends Seeder
{
    public function run(): void
    {
        $userId = DB::table('users')->value('id_user');

        if (! $userId) {
            $this->command?->warn('Tabel users masih kosong. Buat minimal 1 akun user terlebih dahulu sebelum menjalankan BeritaSeeder.');

            return;
        }

        Storage::disk('public')->makeDirectory('berita');
        Storage::disk('public')->makeDirectory('berita/konten');

        $apiOrigin = rtrim(preg_replace('#/api/v1/?$#', '', (string) env('API_BASE_URL', 'http://127.0.0.1:8001')), '/');

        $hasKategori = Schema::hasColumn('berita', 'id_kategori');
        $kategoriMap = Schema::hasTable('kategori')
            ? DB::table('kategori')->pluck('id_kategori', 'nama_kategori')
            : collect();

        $daftarBerita = [
            [
                'slug' => 'ae2-star-technology',
                'judul_berita' => 'Jaringan Auto-Crafting AE2 di Star Technology Akhirnya Bebas Bottleneck Setelah 14 Jam Begadang',
                'kategori' => 'Info Akademik',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(1),
                'warna' => [15, 76, 129],
                'label_sampul' => 'STAR TECHNOLOGY // AE2 AUTO-CRAFTING',
                'label_konten' => 'TOPOLOGI P2P TUNNEL & MOLECULAR ASSEMBLER',
                'caption_konten' => 'Dokumentasi arsitektur sub-jaringan ME Controller dan P2P Tunnel bebas channel bottleneck.',
                'isi_html' => '<p>Setelah melalui drama kekurangan channel kabel ME dan tumpukan resep crafting yang saling bertabrakan, infrastruktur <strong>Applied Energistics 2 (AE2)</strong> di modpack <em>Star Technology</em> akhirnya berhasil beroperasi penuh tanpa macet.</p>'
                    .'<h3>Perombakan Jalur P2P Tunnel</h3>'
                    .'<p>Perbaikan utama dilakukan dengan merombak jalur P2P Tunnel serta memisahkan blok <strong>Molecular Assembler</strong> dan <strong>Pattern Provider</strong> ke dalam sub-jaringan khusus pemrosesan bijih dan komponen sirkuit.</p>'
                    .'__FOTO_KONTEN__'
                    .'<ul><li>Kapasitas 32 channel per kabel Dense ME termanfaatkan secara merata tanpa tabrakan jalur.</li><li>Waktu pembuatan sirkuit tingkat lanjut berkurang hingga 65 persen.</li><li>Pemantauan stok bahan baku berjalan otomatis melalui ME Level Emitter.</li></ul>'
                    .'<p>Kini seluruh permintaan komponen mesin tingkat lanjut dapat diproduksi secara otomatis hanya dengan satu kali klik.</p>',
            ],
            [
                'slug' => 'ai-spasisense-trk',
                'judul_berita' => 'Mahasiswa TRK Berhasil Melatih AI untuk Mendeteksi Mood Dosen dari Cara Mengetik Spasi',
                'kategori' => 'Prestasi Mahasiswa',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(2),
                'warna' => [30, 64, 175],
                'label_sampul' => 'RISET INOVASI // SPASISENSE V1.0',
                'label_konten' => 'GRAFIK FREKUENSI KETUKAN TOMBOL SPASI',
                'caption_konten' => 'Hasil pengujian sinyal akustik keyboard mekanikal saat sesi presentasi sidang proyek.',
                'isi_html' => '<p>Tim riset gabungan mahasiswa <strong>Teknologi Rekayasa Komputer</strong> mengklaim telah menemukan terobosan baru dalam bidang pemrosesan sinyal akustik berbasis kecerdasan buatan yang diberi nama <strong>SpasiSense v1.0</strong>.</p>'
                    .'<blockquote>&ldquo;Dari jeda antar ketukan tombol spasi, model klasifikasi mampu memetakan tingkat ketegangan ruangan secara real-time.&rdquo;</blockquote>'
                    .'__FOTO_KONTEN__'
                    .'<p>Sistem ini menganalisis frekuensi ketukan tombol spasi pada keyboard mekanikal dan membandingkannya dengan kecepatan kursor saat presentasi berlangsung. Akurasinya diklaim mencapai <strong>99,4 persen</strong> dalam memprediksi apakah sesi tanya jawab akan berjalan damai atau penuh pertanyaan jebakan.</p>',
            ],
            [
                'slug' => 'closed-loop-chemical-gregtech',
                'judul_berita' => 'Reaktor Nuklir dan Closed-Loop Chemical Processing Star Technology Sukses Beroperasi Stabil',
                'kategori' => 'Lomba',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(3),
                'warna' => [6, 95, 70],
                'label_sampul' => 'GREGTECH // CLOSED-LOOP CHEMICAL LINE',
                'label_konten' => 'DIAGRAM DAUR ULANG ASAM SULFAT & PTFE',
                'caption_konten' => 'Dokumentasi lini reaktor kimia tertutup untuk produksi Polytetrafluoroethylene (PTFE) dan Titanium.',
                'isi_html' => '<p>Proyek pembangunan pembangkit listrik tenaga nuklir dan rantai pemrosesan kimia tertutup (<em>closed-loop chemical processing</em>) di base utama <strong>Star Technology</strong> resmi dinyatakan lolos uji beban penuh.</p>'
                    .'<h3>Daur Ulang Fluida Otomatis</h3>'
                    .'<p>Seluruh residu reaksi kimia seperti <strong>asam sulfat</strong>, <strong>klorin</strong>, dan <strong>hidrogen</strong> kini didaur ulang secara otomatis menggunakan jaringan pipa fluida bertekanan tinggi untuk memasok lini produksi <em>Polytetrafluoroethylene (PTFE)</em> dan pengolahan logam titanium.</p>'
                    .'__FOTO_KONTEN__'
                    .'<p>Dengan surplus daya yang sangat besar, seluruh mesin multiblock GregTech dapat berjalan bersamaan pada <em>overclock</em> maksimal tanpa risiko kabel terbakar maupun ledakan reaktor di tengah malam.</p>',
            ],
            [
                'slug' => 'inkubator-esp32-fuzzy',
                'judul_berita' => 'Tragedi Lab IoT: Lupa Pasang Heatsink pada ESP32, Inkubator Telur Malah Sukses Bikin Telur Ceplok',
                'kategori' => 'Info Akademik',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(4),
                'warna' => [180, 83, 9],
                'label_sampul' => 'PROTOTIPE IOT // ESP32 FUZZY CONTROL',
                'label_konten' => 'LOG SENSOR SUHU DS18B20 & RELAY PEMANAS',
                'caption_konten' => 'Catatan pengujian modul pemanas dan pembacaan sensor DS18B20 sebelum kalibrasi ulang.',
                'isi_html' => '<p>Uji coba prototipe sistem pemantauan inkubator pintar berbasis <strong>ESP32</strong> dan sensor suhu <strong>DS18B20</strong> mengalami belokan tak terduga akibat kesalahan kalibrasi nilai aturan pada kontroler <em>Fuzzy Logic</em>.</p>'
                    .'__FOTO_KONTEN__'
                    .'<p>Alih-alih menjaga suhu ruang penetasan tetap stabil di angka 37,5 derajat Celsius, elemen pemanas justru bekerja pada siklus penuh selama dua puluh menit tanpa henti karena sensor pembacaan sempat terlepas dari dudukannya.</p>'
                    .'<p>Beruntung tidak ada komponen yang rusak, dan tim pengembang memutuskan hasil uji coba tersebut tetap layak dikonsumsi sebagai sarapan pagi sebelum kelas praktikum dimulai.</p>',
            ],
            [
                'slug' => 'kabel-lan-cat6-lab',
                'judul_berita' => 'Ditemukan Spesies Baru di Ruang Lab Jaringan: Kabel LAN Cat6 yang Bisa Melilit Sendiri',
                'kategori' => 'Kunjungan Industri',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(5),
                'warna' => [67, 56, 202],
                'label_sampul' => 'LAB JARINGAN // ANOMALI KABEL UTP CAT6',
                'label_konten' => 'DOKUMENTASI TOPOLOGI MEJA PRAKTIKUM OSPF',
                'caption_konten' => 'Kondisi rak perkabelan lab jaringan setelah ditinggal istirahat selama 45 menit.',
                'isi_html' => '<p>Fenomena misterius kembali terjadi di meja praktikum jaringan komputer. Dua puluh kabel <strong>UTP Cat6</strong> yang sebelumnya sudah digulung rapi menggunakan velcro ditemukan kembali membentuk simpul mati hanya dalam waktu 45 menit saat ditinggal makan siang.</p>'
                    .'__FOTO_KONTEN__'
                    .'<p>Para peneliti menduga simpul tersebut terbentuk akibat kombinasi medan magnet dari router Cisco yang sedang menjalankan protokol OSPF dan hukum entropi ruang laboratorium yang tidak pernah beres.</p>',
            ],
            [
                'slug' => 'ebf-paralel-star-tech',
                'judul_berita' => 'Krisis Sirkuit HV dan Plat Baja Star Technology Teratasi Berkat Lini Electric Blast Furnace Terpadu',
                'kategori' => 'Magang',
                'status' => 'publish',
                'tanggal_publish' => now()->subDays(6),
                'warna' => [17, 94, 89],
                'label_sampul' => 'EXPANSI PABRIK // ELECTRIC BLAST FURNACE',
                'label_konten' => '3 UNIT EBF PARALEL DENGAN SUPLAI OKSIGEN',
                'caption_konten' => 'Susunan tiga unit Electric Blast Furnace multiblock dengan input bus terpisah.',
                'isi_html' => '<p>Kelangkaan stok ingot baja dan komponen sirkuit tegangan tinggi (HV) yang sempat menghambat ekspansi pabrik di <strong>Star Technology</strong> akhirnya berhasil diatasi setelah penambahan tiga unit <strong>Electric Blast Furnace (EBF)</strong> paralel.</p>'
                    .'__FOTO_KONTEN__'
                    .'<p>Setiap tungku peleburan dilengkapi dengan input bus terpisah dan sistem suplai oksigen otomatis sehingga waktu peleburan berkurang hingga setengahnya.</p>',
            ],
            [
                'slug' => 'draf-ubin-piezoelektrik',
                'judul_berita' => 'Draf Rahasia: Rencana Pembangkit Listrik Kampus Tenaga Kinetik Mahasiswa Lari Mengejar Absen',
                'kategori' => 'Info Akademik',
                'status' => 'draft',
                'tanggal_publish' => null,
                'warna' => [71, 85, 105],
                'label_sampul' => 'DRAF INTERNAL // ENERGI KINETIK KORIDOR',
                'label_konten' => null,
                'caption_konten' => null,
                'isi_html' => '<p>Dokumen konsep awal pemanfaatan ubin piezoelektrik di koridor gedung perkuliahan untuk memanen energi kinetik dari langkah kaki mahasiswa yang berlari pada pukul 07.29 WIB.</p>'
                    .'<p>Artikel ini masih berstatus draf internal sehingga tidak boleh tampil pada halaman publik landing page.</p>',
            ],
        ];

        foreach ($daftarBerita as $item) {
            $sampulPath = 'berita/seeder-sampul-'.$item['slug'].'.png';
            $this->buatGambarPng(
                Storage::disk('public')->path($sampulPath),
                1200,
                630,
                $item['warna'],
                $item['label_sampul'],
                $item['judul_berita']
            );

            $isiFinal = $item['isi_html'];
            if (! empty($item['label_konten'])) {
                $kontenPath = 'berita/konten/seeder-konten-'.$item['slug'].'.png';
                $this->buatGambarPng(
                    Storage::disk('public')->path($kontenPath),
                    960,
                    540,
                    [
                        max(0, $item['warna'][0] - 10),
                        min(255, $item['warna'][1] + 20),
                        min(255, $item['warna'][2] + 25),
                    ],
                    'DOKUMENTASI KEGIATAN // TRK SIKAP',
                    $item['label_konten']
                );

                $urlKonten = $apiOrigin.'/storage/'.$kontenPath;
                $figureHtml = '<figure class="image"><img src="'.e($urlKonten).'" alt="'.e($item['label_konten']).'"><figcaption>'.e($item['caption_konten'] ?? '').'</figcaption></figure>';
                $isiFinal = str_replace('__FOTO_KONTEN__', $figureHtml, $isiFinal);
            } else {
                $isiFinal = str_replace('__FOTO_KONTEN__', '', $isiFinal);
            }

            $payload = [
                'id_user' => $userId,
                'id_kegiatan' => null,
                'isi_berita' => $isiFinal,
                'status' => $item['status'],
                'tanggal_publish' => $item['tanggal_publish'],
                'sampul' => $sampulPath,
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

        $this->command?->info('BeritaSeeder berhasil dijalankan (6 berita publish dengan sampul & foto dokumentasi, 1 berita draft).');
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function buatGambarPng(string $fullPath, int $width, int $height, array $rgb, string $subjudul, string $judul): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            // Fallback PNG 1x1 valid jika ekstensi GD tidak aktif
            $png1x1 = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            file_put_contents($fullPath, $png1x1);

            return;
        }

        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        $accent = imagecolorallocate($img, min(255, $rgb[0] + 35), min(255, $rgb[1] + 45), min(255, $rgb[2] + 55));
        $grid = imagecolorallocate($img, min(255, $rgb[0] + 18), min(255, $rgb[1] + 22), min(255, $rgb[2] + 28));
        $white = imagecolorallocate($img, 255, 255, 255);
        $badgeBg = imagecolorallocate($img, 255, 255, 255);
        $badgeText = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);

        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        // Pola grid dekoratif
        for ($x = 0; $x < $width; $x += 60) {
            imageline($img, $x, 0, $x, $height, $grid);
        }
        for ($y = 0; $y < $height; $y += 60) {
            imageline($img, 0, $y, $width, $y, $grid);
        }

        // Panel aksen diagonal/kanan
        imagefilledrectangle($img, (int) ($width * 0.72), 0, $width, $height, $accent);
        imagefilledrectangle($img, 64, 64, 64 + min($width - 128, (strlen($subjudul) * 10) + 32), 102, $badgeBg);
        imagestring($img, 4, 80, 75, substr($subjudul, 0, 70), $badgeText);

        // Bungkus teks judul menjadi beberapa baris
        $baris = explode("\n", wordwrap($judul, 46, "\n", true));
        $yText = (int) ($height * 0.36);
        foreach (array_slice($baris, 0, 4) as $line) {
            imagestring($img, 5, 64, $yText, $line, $white);
            $yText += 32;
        }

        imagestring($img, 3, 64, $height - 64, 'SIKAP TRK // Teknologi Rekayasa Komputer', $white);

        imagepng($img, $fullPath);
        imagedestroy($img);
    }
}
