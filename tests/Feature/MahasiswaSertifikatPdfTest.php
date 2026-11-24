<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MahasiswaSertifikatPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function buatMahasiswa(string $email): User
    {
        $id = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    public function test_gagal_upload_jika_bukan_pdf_atau_ukuran_melebihi_5mb(): void
    {
        Storage::fake('local');
        $user = $this->buatMahasiswa('sertifikat-validasi@test.local');

        $fileBukanPdf = UploadedFile::fake()->create('sertifikat.jpg', 500, 'image/jpeg');
        $fileKebesaran = UploadedFile::fake()->create('sertifikat-besar.pdf', 6000, 'application/pdf');

        $payloadDasar = [
            'sumber' => 'mandiri',
            'nama_lomba' => 'Gemastik XVII',
            'penyelenggara' => 'Kemendikbud',
            'lokasi' => 'Semarang',
            'tingkat' => 'Nasional',
            'capaian' => 'Juara 1',
            'jenis' => 'Akademik',
            'tanggal_mulai' => '2026-10-01',
            'tanggal_selesai' => '2026-10-03',
            'bentuk' => 'Tim',
        ];

        $resTipe = $this->actingAs($user)->post(route('api.mahasiswa.prestasi.simpan'), array_merge($payloadDasar, [
            'dokumen' => [
                ['jenis' => 'Sertifikat', 'file' => $fileBukanPdf],
            ],
        ]));
        $resTipe->assertStatus(422)->assertJsonValidationErrors(['dokumen.0.file']);

        $resUkuran = $this->actingAs($user)->post(route('api.mahasiswa.prestasi.simpan'), array_merge($payloadDasar, [
            'dokumen' => [
                ['jenis' => 'Sertifikat', 'file' => $fileKebesaran],
            ],
        ]));
        $resUkuran->assertStatus(422)->assertJsonValidationErrors(['dokumen.0.file']);
    }

    public function test_berhasil_upload_dan_unduh_sertifikat_pdf_dengan_hak_akses(): void
    {
        Storage::fake('local');
        $pemilik = $this->buatMahasiswa('pemilik-sertifikat@test.local');
        $mahasiswaLain = $this->buatMahasiswa('mahasiswa-lain@test.local');

        $filePdf = UploadedFile::fake()->create('Sertifikat Juara 1!!.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($pemilik)->post(route('api.mahasiswa.prestasi.simpan'), [
            'sumber' => 'mandiri',
            'nama_lomba' => 'Hackathon Nasional',
            'penyelenggara' => 'Kominfo',
            'lokasi' => 'Jakarta',
            'tingkat' => 'Nasional',
            'capaian' => 'Juara 1',
            'jenis' => 'Akademik',
            'tanggal_mulai' => '2026-10-10',
            'tanggal_selesai' => '2026-10-12',
            'bentuk' => 'Individu',
            'dokumen' => [
                ['jenis' => 'Sertifikat', 'file' => $filePdf],
            ],
        ]);

        $response->assertSessionHasNoErrors();

        $bukti = DB::table('bukti_prestasi')->first();
        $this->assertNotNull($bukti);
        $this->assertEquals('sertifikat-juara-1.pdf', $bukti->nama_file);
        Storage::disk('local')->assertExists($bukti->link);

        $this->actingAs($mahasiswaLain)
            ->get(route('api.mahasiswa.prestasi.bukti.file', $bukti->id_bukti_prestasi))
            ->assertStatus(403);

        $this->actingAs($pemilik)
            ->get(route('api.mahasiswa.prestasi.bukti.file', $bukti->id_bukti_prestasi))
            ->assertStatus(200);
    }
}
