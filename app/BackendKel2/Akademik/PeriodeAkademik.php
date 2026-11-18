<?php

namespace App\BackendKel2\Akademik;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PeriodeAkademik
{
    protected $tahunAkademik;

    protected $semester;

    public function __construct($tahunAkademik, $semester = 'Ganjil')
    {
        $this->tahunAkademik = $tahunAkademik; // Contoh: "2026/2027" atau dari database
        $this->semester = $semester;         // "Ganjil" atau "Genap"
    }

    /**
     * Mendapatkan periode akademik yang sedang aktif saat ini.
     * Mengambil dari tabel 'tahun_akademik' yang status-nya true,
     * atau fallback otomatis berdasarkan tanggal sistem jika tabel kosong.
     */
    public static function sekarang()
    {
        // Cek data aktif dari database tabel 'tahun_akademik'
        $aktif = DB::table('tahun_akademik')->where('status', true)->first();

        if ($aktif) {
            // Asumsi format string di DB: "Ganjil 2026/2027" atau "2026/2027 Ganjil"
            // Kita pisahkan teksnya secara fleksibel
            $teks = $aktif->tahun_akademik;
            if (str_contains(strtolower($teks), 'ganjil')) {
                $semester = 'Ganjil';
                $tahun = trim(str_ireplace('ganjil', '', $teks));
            } elseif (str_contains(strtolower($teks), 'genap')) {
                $semester = 'Genap';
                $tahun = trim(str_ireplace('genap', '', $teks));
            } else {
                $semester = 'Ganjil';
                $tahun = $teks;
            }

            return new static($tahun, $semester);
        }

        // Fallback otomatis berdasarkan bulan berjalan (jika tabel belum di-seed)
        $now = Carbon::now();
        $year = $now->year;
        $month = $now->month;

        // Asumsi: Ganjil mulai Agustus (8), Genap mulai Februari (2)
        if ($month >= 8 || $month <= 1) {
            $tahunAkademik = ($month <= 1) ? ($year - 1).'/'.$year : $year.'/'.($year + 1);

            return new static($tahunAkademik, 'Ganjil');
        } else {
            $tahunAkademik = ($year - 1).'/'.$year;

            return new static($tahunAkademik, 'Genap');
        }
    }

    /**
     * Mengembalikan label lengkap, contoh: "2026/2027 Ganjil"
     */
    public function label()
    {
        return "{$this->tahunAkademik} {$this->semester}";
    }

    /**
     * Menghitung semester ke berapa suatu angkatan saat ini.
     * Contoh: Angkatan 2024 pada periode Ganjil 2026/2027 adalah semester 5.
     */
    public function semesterKe($angkatan)
    {
        [$tahunMulai] = explode('/', $this->tahunAkademik);
        $selisihTahun = intval($tahunMulai) - intval($angkatan);

        if ($this->semester === 'Ganjil') {
            return ($selisihTahun * 2) + 1;
        } else {
            return ($selisihTahun * 2) + 2;
        }
    }

    /**
     * Tanggal mulai periode (untuk fungsi pencarian/filter tanggal).
     */
    public function mulai()
    {
        [$tahunMulai] = explode('/', $this->tahunAkademik);
        if ($this->semester === 'Ganjil') {
            return Carbon::createFromDate($tahunMulai, 8, 1)->startOfDay(); // 1 Agustus
        } else {
            [, $tahunSelesai] = explode('/', $this->tahunAkademik);

            return Carbon::createFromDate($tahunSelesai, 2, 1)->startOfDay(); // 1 Februari
        }
    }

    /**
     * Tanggal selesai periode.
     */
    public function selesai()
    {
        [, $tahunSelesai] = explode('/', $this->tahunAkademik);
        if ($this->semester === 'Ganjil') {
            return Carbon::createFromDate($tahunSelesai, 1, 31)->endOfDay(); // 31 Januari
        } else {
            return Carbon::createFromDate($tahunSelesai, 7, 31)->endOfDay(); // 31 Juli
        }
    }

    /**
     * Daftar pilihan untuk mengisi dropdown Tahun Akademik.
     */
    public static function daftarTahun()
    {
        return DB::table('tahun_akademik')->pluck('tahun_akademik');
    }
}
