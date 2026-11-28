<?php

namespace Tests\Feature\BackendKel2;

use App\BackendKel2\Dokumen\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    private ImageUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->service = new ImageUploadService;
    }

    // ===== HAPPY PATH =====

    public function test_simpan_jpg_foto_profil(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $path = $this->service->simpan($file, 'foto-profil');

        $this->assertStringStartsWith('foto-profil/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_simpan_png_foto_profil(): void
    {
        $file = UploadedFile::fake()->image('avatar.png', 300, 300);

        $path = $this->service->simpan($file, 'foto-profil');

        $this->assertStringStartsWith('foto-profil/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_simpan_webp_sampul_berita(): void
    {
        $file = UploadedFile::fake()->image('cover.webp', 800, 400);

        $path = $this->service->simpan($file, 'sampul-berita');

        $this->assertStringStartsWith('berita/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_simpan_jpg_sampul_berita(): void
    {
        $file = UploadedFile::fake()->image('cover.jpg', 1200, 630);

        $path = $this->service->simpan($file, 'sampul-berita');

        $this->assertStringStartsWith('berita/', $path);
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_nama_file_tidak_mengandung_nama_asli(): void
    {
        $file = UploadedFile::fake()->image('rahasia-privasi.jpg', 100, 100);

        $path = $this->service->simpan($file, 'foto-profil');

        $this->assertStringNotContainsString('rahasia', $path);
        $this->assertStringNotContainsString('privasi', $path);
    }

    public function test_path_kompatibel_dengan_linkfile(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        $path = $this->service->simpan($file, 'foto-profil');

        // Path relatif tanpa leading slash, tanpa 'storage/' prefix
        $this->assertDoesNotMatchRegularExpression('/^\//', $path);
        $this->assertDoesNotMatchRegularExpression('/^storage\//', $path);
        $this->assertDoesNotMatchRegularExpression('/^https?:\/\//', $path);
    }

    // ===== PENOLAKAN =====

    public function test_tolak_pdf(): void
    {
        $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        $this->expectException(ValidationException::class);
        $this->service->simpan($file, 'foto-profil');
    }

    public function test_tolak_svg(): void
    {
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>';
        $tmpFile = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($tmpFile, $svgContent);

        $file = new UploadedFile($tmpFile, 'icon.svg', 'image/svg+xml', null, true);

        try {
            $this->service->simpan($file, 'foto-profil');
            $this->fail('Seharusnya menolak SVG.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_tolak_file_bukan_gambar_berekstensi_jpg(): void
    {
        // Buat file teks biasa yang diberi ekstensi .jpg
        $tmpFile = tempnam(sys_get_temp_dir(), 'fake');
        file_put_contents($tmpFile, 'ini bukan gambar, hanya teks biasa');

        $file = new UploadedFile($tmpFile, 'palsu.jpg', 'image/jpeg', null, true);

        try {
            $this->service->simpan($file, 'foto-profil');
            $this->fail('Seharusnya menolak berkas teks berekstensi .jpg.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        } finally {
            @unlink($tmpFile);
        }
    }

    public function test_tolak_foto_profil_terlalu_besar(): void
    {
        // 3 MB > batas 2 MB
        $file = UploadedFile::fake()->image('besar.jpg', 100, 100)->size(3 * 1024);

        $this->expectException(ValidationException::class);
        $this->service->simpan($file, 'foto-profil');
    }

    public function test_tolak_sampul_berita_terlalu_besar(): void
    {
        // 5 MB > batas 4 MB
        $file = UploadedFile::fake()->image('besar.jpg', 100, 100)->size(5 * 1024);

        $this->expectException(ValidationException::class);
        $this->service->simpan($file, 'sampul-berita');
    }

    public function test_tolak_tujuan_tidak_dikenal(): void
    {
        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $this->expectException(ValidationException::class);
        $this->service->simpan($file, 'avatar-kustom');
    }

    public function test_tolak_ekstensi_ganda(): void
    {
        $file = UploadedFile::fake()->image('shell.php.jpg', 100, 100);

        $this->expectException(ValidationException::class);
        $this->service->simpan($file, 'foto-profil');
    }

    public function test_error_key_file(): void
    {
        $file = UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf');

        try {
            $this->service->simpan($file, 'foto-profil');
            $this->fail('Seharusnya melempar ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
    }

    public function test_tolak_dimensi_sisi_terpanjang_di_atas_batas(): void
    {
        $file = UploadedFile::fake()->image('raksasa.jpg', 5001, 1);

        $this->expectException(ValidationException::class);
        $this->service->simpan($file, 'sampul-berita');
    }

    // ===== PENGHAPUSAN AMAN =====

    public function test_hapus_menghapus_path_upload_milik_service(): void
    {
        Storage::disk('public')->put('foto-profil/aman.jpg', 'isi gambar');
        Storage::disk('public')->put('berita/sampul.png', 'isi gambar');

        $this->service->hapus('foto-profil/aman.jpg');
        $this->service->hapus('berita/sampul.png');

        Storage::disk('public')->assertMissing('foto-profil/aman.jpg');
        Storage::disk('public')->assertMissing('berita/sampul.png');
    }

    public function test_hapus_aman_untuk_null_dan_url_eksternal(): void
    {
        Storage::disk('public')->put('foto-profil/tetap.jpg', 'isi gambar');

        $this->service->hapus(null);
        $this->service->hapus('https://example.test/foto.jpg');
        $this->service->hapus('http://example.test/foto.jpg');

        Storage::disk('public')->assertExists('foto-profil/tetap.jpg');
    }

    public function test_hapus_menolak_path_traversal(): void
    {
        Storage::disk('public')->put('.env', 'harus tetap aman');
        Storage::disk('public')->put('x', 'harus tetap aman');

        $this->service->hapus('berita/../.env');
        $this->service->hapus('foto-profil/../../x');

        Storage::disk('public')->assertExists('.env');
        Storage::disk('public')->assertExists('x');
    }

    public function test_hapus_menolak_backslash_absolute_dan_subfolder(): void
    {
        Storage::disk('public')->put('foto-profil/tetap.jpg', 'isi gambar');
        Storage::disk('public')->put('berita/konten/tetap.jpg', 'isi gambar');

        $this->service->hapus('foto-profil\\tetap.jpg');
        $this->service->hapus('/foto-profil/tetap.jpg');
        $this->service->hapus('berita/konten/tetap.jpg');

        Storage::disk('public')->assertExists('foto-profil/tetap.jpg');
        Storage::disk('public')->assertExists('berita/konten/tetap.jpg');
    }
}
