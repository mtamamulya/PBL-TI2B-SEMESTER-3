<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * KONTRAK NOTIFIKASI ADMIN & KAPRODI: acuan semua fase (lonceng, halaman, API).
 * Bentuk satu item di sini = bentuk satu item di respons API Fase 2
 * (GET /api/v1/{admin|kaprodi}/notifikasi). Lihat docs/kontrak-notifikasi-staf.md.
 *
 * FASE 1: MODE DUMMY. Data contoh di bawah, status "dibaca" disimpan di session.
 * Fase 3 mengganti isi daftar() dan jumlahBelumDibaca() dengan panggilan API; bentuk item tidak berubah.
 */
class NotifikasiStaf
{
    public const PERAN = ['admin', 'kaprodi'];

    /** Jenis notifikasi = isi filter halaman. */
    public const JENIS = ['kegiatan', 'berita'];

    public const KELOMPOK = ['Hari ini', 'Kemarin', 'Lebih lama'];

    /** Kunci yang WAJIB ada di setiap item. */
    public const KUNCI_ITEM = ['id', 'type', 'title', 'body', 'time', 'g', 'unread', 'href', 'waktu_iso'];

    public static function modeDummy(): bool
    {
        // FASE 3: ubah menjadi "bukan di test dan NOTIF_DUMMY=true" (seperti ProfilProdi::modeDummy).
        return filter_var(env('NOTIF_DUMMY', true), FILTER_VALIDATE_BOOLEAN);
    }

    /** Nama kunci session untuk daftar id yang sudah dibaca (hanya mode dummy). */
    public static function kunciSession(string $peran): string
    {
        return 'notifikasi_staf_dibaca.'.$peran;
    }

    /**
     * Daftar notifikasi satu peran, terbaru di atas.
     *
     * @param  list<string>  $dibaca  id yang sudah dibaca pengguna ini
     * @return list<array<string, mixed>>
     */
    public static function daftar(string $peran, array $dibaca = []): array
    {
        $baris = array_map(function (array $b) use ($dibaca) {
            $waktu = Carbon::now()->subMinutes($b['menit_lalu'])->locale('id');

            return [
                'id' => $b['id'],
                'type' => $b['type'],
                'title' => $b['title'],
                'body' => $b['body'],
                'time' => self::formatWaktu($waktu),
                'g' => self::kelompokWaktu($waktu),
                'unread' => ! $b['sudah_dibaca'] && ! in_array($b['id'], $dibaca, true),
                'href' => $b['href'],
                'waktu_iso' => $waktu->toIso8601String(),
            ];
        }, self::bawaan($peran));

        usort($baris, fn ($a, $b) => strcmp($b['waktu_iso'], $a['waktu_iso']));

        return $baris;
    }

    /** @return list<string> */
    public static function semuaId(string $peran): array
    {
        return array_column(self::bawaan($peran), 'id');
    }

    public static function idValid(string $peran, string $id): bool
    {
        return in_array($id, self::semuaId($peran), true);
    }

    /**
     * Angka lonceng. null = tidak ada angka (gagal mengambil data); lonceng tetap tampil tanpa angka
     * dan halaman TIDAK boleh error karenanya. Fase 3: ambil dari API + cache pendek.
     */
    public static function jumlahBelumDibaca(string $peran): ?int
    {
        try {
            $dibaca = (array) session(self::kunciSession($peran), []);

            return count(array_filter(self::daftar($peran, $dibaca), fn ($n) => $n['unread']));
        } catch (Throwable) {
            return null;
        }
    }

    public static function kelompokWaktu(?Carbon $waktu): string
    {
        if (! $waktu) {
            return 'Lebih lama';
        }
        if ($waktu->isToday()) {
            return 'Hari ini';
        }
        if ($waktu->isYesterday()) {
            return 'Kemarin';
        }

        return 'Lebih lama';
    }

    public static function formatWaktu(?Carbon $waktu): string
    {
        if (! $waktu) {
            return '-';
        }

        return ($waktu->isToday() || $waktu->isYesterday())
            ? $waktu->format('H.i')
            : $waktu->translatedFormat('j M Y');
    }

    /**
     * Data contoh. Aturan isi per peran (sama dengan yang akan dibuat API Fase 2):
     *  - admin   : pengajuan masuk/diajukan ulang, keputusan kaprodi, berita diajukan
     *  - kaprodi : pengajuan menunggu tanda tangan, hasil tinjauan admin atas berita MILIK kaprodi
     *
     * @return list<array<string, mixed>>
     */
    private static function bawaan(string $peran): array
    {
        $daftarKegiatan = route($peran.'.pengajuan');
        $daftarBerita = route($peran.'.berita');

        if ($peran === 'kaprodi') {
            return [
                ['id' => 'k-9011', 'type' => 'kegiatan', 'menit_lalu' => 90, 'sudah_dibaca' => false, 'href' => $daftarKegiatan,
                    'title' => 'Menunggu tanda tangan Anda',
                    'body' => 'Pengajuan "Juara 2 Lomba Cipta Aplikasi Nasional" sudah disetujui admin dan menunggu tanda tangan Anda.'],
                ['id' => 'k-9012', 'type' => 'kegiatan', 'menit_lalu' => 1550, 'sudah_dibaca' => false, 'href' => $daftarKegiatan,
                    'title' => 'Menunggu tanda tangan Anda',
                    'body' => 'Pengajuan "Pelatihan IoT untuk Guru SMK" sudah disetujui admin dan menunggu tanda tangan Anda.'],
                ['id' => 'k-9013', 'type' => 'kegiatan', 'menit_lalu' => 4200, 'sudah_dibaca' => true, 'href' => $daftarKegiatan,
                    'title' => 'Menunggu tanda tangan Anda',
                    'body' => 'Pengajuan "Seminar Nasional Teknologi Rekayasa" sudah disetujui admin dan menunggu tanda tangan Anda.'],
                ['id' => 'b-9111-publish', 'type' => 'berita', 'menit_lalu' => 300, 'sudah_dibaca' => false, 'href' => $daftarBerita,
                    'title' => 'Berita Anda dipublikasikan',
                    'body' => 'Berita "Profil Prodi TRK 2026" telah ditinjau admin dan dipublikasikan.'],
                ['id' => 'b-9112-direvisi', 'type' => 'berita', 'menit_lalu' => 2900, 'sudah_dibaca' => false, 'href' => $daftarBerita,
                    'title' => 'Berita Anda perlu direvisi',
                    'body' => 'Berita "Kunjungan Industri Semester Ganjil" dikembalikan admin. Catatan: Tambahkan foto sampul.'],
                ['id' => 'b-9113-ditolak', 'type' => 'berita', 'menit_lalu' => 9500, 'sudah_dibaca' => true, 'href' => $daftarBerita,
                    'title' => 'Berita Anda ditolak',
                    'body' => 'Berita "Pengumuman Libur" ditolak admin. Catatan: Isi berita belum sesuai kebijakan.'],
            ];
        }

        return [
            ['id' => 'k-9001', 'type' => 'kegiatan', 'menit_lalu' => 40, 'sudah_dibaca' => false, 'href' => $daftarKegiatan,
                'title' => 'Pengajuan baru masuk',
                'body' => 'Kegiatan "Juara 2 Lomba Cipta Aplikasi Nasional" diajukan oleh Budi Santoso (Mahasiswa) dan menunggu peninjauan.'],
            ['id' => 'k-9002', 'type' => 'kegiatan', 'menit_lalu' => 150, 'sudah_dibaca' => false, 'href' => $daftarKegiatan,
                'title' => 'Pengajuan diajukan ulang',
                'body' => 'Dr. Rina Wulandari memperbaiki dan mengajukan ulang "Pelatihan IoT untuk Guru SMK".'],
            ['id' => 'b-9101-diajukan', 'type' => 'berita', 'menit_lalu' => 200, 'sudah_dibaca' => false, 'href' => $daftarBerita,
                'title' => 'Berita menunggu tinjauan',
                'body' => 'Berita "Tim TRK Raih Juara di KMIPN" diajukan oleh Ahmad Fauzi dan menunggu tinjauan.'],
            ['id' => 'k-9003', 'type' => 'kegiatan', 'menit_lalu' => 1500, 'sudah_dibaca' => false, 'href' => $daftarKegiatan,
                'title' => 'Kaprodi menyetujui pengajuan',
                'body' => 'Surat untuk "Seminar Nasional Teknologi Rekayasa" sudah ditandatangani Kaprodi.'],
            ['id' => 'k-9004', 'type' => 'kegiatan', 'menit_lalu' => 1700, 'sudah_dibaca' => false, 'href' => $daftarKegiatan,
                'title' => 'Kaprodi mengembalikan pengajuan',
                'body' => 'Pengajuan "Workshop Embedded System" dikembalikan untuk direvisi. Catatan: Lengkapi lampiran peserta.'],
            ['id' => 'b-9102-diajukan', 'type' => 'berita', 'menit_lalu' => 4000, 'sudah_dibaca' => true, 'href' => $daftarBerita,
                'title' => 'Berita menunggu tinjauan',
                'body' => 'Berita "Kunjungan Industri ke Semarang" diajukan oleh Dr. Rina Wulandari dan menunggu tinjauan.'],
            ['id' => 'k-9005', 'type' => 'kegiatan', 'menit_lalu' => 9000, 'sudah_dibaca' => true, 'href' => $daftarKegiatan,
                'title' => 'Kaprodi menolak pengajuan',
                'body' => 'Pengajuan "Studi Banding Luar Negeri" ditolak Kaprodi. Catatan: Anggaran belum disetujui.'],
        ];
    }
}
