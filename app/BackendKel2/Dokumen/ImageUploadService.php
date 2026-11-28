<?php

namespace App\BackendKel2\Dokumen;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service bersama untuk upload gambar (foto profil & sampul berita).
 *
 * Dipakai oleh BE-1 (profil) dan BE-7 (berita) — service ini hanya
 * menyimpan/menghapus berkas; **tidak** membuat endpoint sendiri.
 *
 * Path yang dikembalikan oleh `simpan()` adalah path relatif di disk
 * `public`, misalnya `foto-profil/a1b2c3.jpg` atau `berita/d4e5f6.webp`,
 * dan kompatibel dengan `LinkFile::url()` serta `LinkFile::hapus()`.
 */
class ImageUploadService
{
    /** Batas ukuran foto profil (byte). */
    public const BATAS_UKURAN_FOTO_PROFIL = 2 * 1024 * 1024;

    /** Batas ukuran sampul berita (byte). */
    public const BATAS_UKURAN_SAMPUL_BERITA = 4 * 1024 * 1024;

    /** Batas sisi terpanjang gambar (piksel). */
    public const BATAS_DIMENSI_MAKS = 5000;

    /** MIME yang diizinkan beserta ekstensi keluaran. */
    private const MIME_KE_EKSTENSI = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /** Tujuan yang diizinkan beserta folder disk dan batas ukuran. */
    private const TUJUAN = [
        'foto-profil' => [
            'folder' => 'foto-profil',
            'batas' => self::BATAS_UKURAN_FOTO_PROFIL,
        ],
        'sampul-berita' => [
            'folder' => 'berita',
            'batas' => self::BATAS_UKURAN_SAMPUL_BERITA,
        ],
    ];

    /**
     * Simpan berkas gambar yang di-upload ke disk `public`.
     *
     * @param  UploadedFile  $file  Berkas dari request (`$request->file(...)`)
     * @param  string  $tujuan  `'foto-profil'` atau `'sampul-berita'`
     * @return string Path relatif di disk public, contoh `foto-profil/uuid.jpg`
     *
     * @throws ValidationException Jika berkas tidak memenuhi syarat
     */
    public function simpan(UploadedFile $file, string $tujuan): string
    {
        $config = self::TUJUAN[$tujuan] ?? null;
        if ($config === null) {
            throw ValidationException::withMessages([
                'file' => "Tujuan upload '{$tujuan}' tidak dikenal.",
            ]);
        }

        // --- Tolak ekstensi ganda (mis. "foto.php.jpg") ---
        $namaAsli = $file->getClientOriginalName();
        $tanpaEksAkhir = pathinfo($namaAsli, PATHINFO_FILENAME);
        if (str_contains($tanpaEksAkhir, '.')) {
            throw ValidationException::withMessages([
                'file' => 'Nama berkas mengandung ekstensi ganda.',
            ]);
        }

        // --- Batas ukuran ---
        if ($file->getSize() > $config['batas']) {
            $batasMb = $config['batas'] / 1024 / 1024;
            throw ValidationException::withMessages([
                'file' => "Ukuran berkas melebihi batas {$batasMb} MB.",
            ]);
        }

        // --- Deteksi MIME dari isi berkas (finfo) ---
        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages([
                'file' => 'Berkas tidak dapat dibaca.',
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($realPath);

        if (! isset(self::MIME_KE_EKSTENSI[$mime])) {
            throw ValidationException::withMessages([
                'file' => 'Format berkas tidak didukung. Hanya JPG, PNG, dan WebP yang diizinkan.',
            ]);
        }

        // --- Validasi struktur gambar dan dimensi ---
        $info = @getimagesize($realPath);
        if ($info === false || ! isset($info[0], $info[1])) {
            throw ValidationException::withMessages([
                'file' => 'Berkas bukan gambar yang valid.',
            ]);
        }

        $lebar = (int) $info[0];
        $tinggi = (int) $info[1];

        if ($lebar <= 0 || $tinggi <= 0) {
            throw ValidationException::withMessages([
                'file' => 'Berkas bukan gambar yang valid.',
            ]);
        }

        if (max($lebar, $tinggi) > self::BATAS_DIMENSI_MAKS) {
            throw ValidationException::withMessages([
                'file' => 'Dimensi gambar terlalu besar. Sisi terpanjang maksimal '.self::BATAS_DIMENSI_MAKS.' piksel.',
            ]);
        }

        // --- Simpan apa adanya dengan nama acak ---
        $ekstensi = self::MIME_KE_EKSTENSI[$mime];
        $namaFile = Str::uuid()->toString().'.'.$ekstensi;

        return $file->storeAs($config['folder'], $namaFile, 'public');
    }

    /**
     * Hapus berkas gambar dari disk `public`.
     *
     * Hanya menghapus path yang cocok persis dengan pola
     * `foto-profil/{nama}` atau `berita/{nama}` (satu level, tanpa subfolder).
     * Aman dipanggil dengan `null`, URL eksternal, atau path di luar folder ini.
     */
    public function hapus(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (! $this->pathBolehDihapus($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /**
     * Periksa apakah path cocok persis pola yang aman untuk dihapus.
     */
    private function pathBolehDihapus(string $path): bool
    {
        // Tolak jika mengandung '..'
        if (str_contains($path, '..')) {
            return false;
        }

        // Tolak jika mengandung backslash
        if (str_contains($path, '\\')) {
            return false;
        }

        // Tolak jika diawali '/'
        if (str_starts_with($path, '/')) {
            return false;
        }

        // Tolak URL eksternal
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return false;
        }

        // Hanya izinkan pola: {folder}/{nama} — tepat satu slash
        $bagian = explode('/', $path);
        if (count($bagian) !== 2) {
            return false;
        }

        [$folder, $nama] = $bagian;

        if ($nama === '' || $folder === '') {
            return false;
        }

        $folderDiizinkan = array_column(self::TUJUAN, 'folder');

        return in_array($folder, $folderDiizinkan, true);
    }
}
