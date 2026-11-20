<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KaprodiPrestasiTest extends TestCase
{
    use DatabaseMigrations;

    private function masukSebagaiKaprodi(): User
    {
        DB::table('roles')->insertOrIgnore(['id_role' => 2, 'nama_role' => 'kaprodi']);

        return User::firstOrCreate(
            ['email' => 'kaprodi@trk.ac.id'],
            ['id_role' => 2, 'password' => 'password', 'status' => true]
        );
    }

    private function palsukanApiPrestasi(): void
    {
        $itemPrestasi = [
            'id_prestasi' => 1,
            'id_kegiatan' => 10,
            'id_prodi' => 1,
            'jenis_prestasi' => 'Juara 1 Nasional',
            'bidang_lomba' => 'Web Development',
            'capaian' => 'Juara 1',
            'catatan' => 'Tim TRK',
            'kegiatan' => [
                'id_kegiatan' => 10,
                'nama_kegiatan' => 'Lomba Web Nasional 2026',
                'tanggal_mulai' => '2026-09-15',
                'tanggal_selesai' => '2026-09-17',
                'tempat' => 'Semarang',
                'deskripsi' => 'Kompetisi pengembangan aplikasi web.',
                'user' => [
                    'id_user' => 5,
                    'nama' => 'Darryll Marchiand',
                    'role' => 'mahasiswa',
                    'is_dosen' => false,
                ],
            ],
            'bukti_prestasi' => [
                [
                    'id_bukti_prestasi' => 100,
                    'file_path' => 'bukti/sertifikat-juara1.pdf',
                    'keterangan' => 'Sertifikat Juara 1',
                    'jenis_dokumen' => [
                        'id_jenis_dokumen' => 1,
                        'nama_jenis_dokumen' => 'Sertifikat',
                    ],
                ],
            ],
        ];

        Http::fake([
            '*/prestasi/1' => Http::response([
                'success' => true,
                'message' => 'Data berhasil diambil',
                'data' => $itemPrestasi,
            ], 200),
            '*/prestasi*' => Http::response([
                'success' => true,
                'message' => 'Daftar prestasi berhasil diambil',
                'data' => [$itemPrestasi],
            ], 200),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('kaprodi.prestasi'));

        $response->assertRedirect(route('login'));
    }

    public function test_kaprodi_can_view_prestasi_list_from_api(): void
    {
        $this->palsukanApiPrestasi();

        $response = $this->actingAs($this->masukSebagaiKaprodi())
            ->withSession(['kaprodi' => ['nama' => 'Kaprodi']])
            ->get(route('kaprodi.prestasi'));

        $response->assertOk();
        $response->assertViewIs('kaprodi.prestasi');
        $response->assertViewHas('rows');

        $rows = $response->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertSame('Lomba Web Nasional 2026', $rows[0]['nama']);
        $this->assertSame('Juara 1 Nasional', $rows[0]['kategori']);
        $this->assertSame('Darryll Marchiand', $rows[0]['pengunggah']);
        $this->assertSame('Ganjil', $rows[0]['semester']);
    }

    public function test_kaprodi_can_view_prestasi_detail_from_api(): void
    {
        $this->palsukanApiPrestasi();

        $response = $this->actingAs($this->masukSebagaiKaprodi())
            ->withSession(['kaprodi' => ['nama' => 'Kaprodi']])
            ->get(route('kaprodi.prestasi.show', 1));

        $response->assertOk();
        $response->assertViewIs('kaprodi.prestasi-detail');
        $response->assertViewHas(['prestasi', 'modelAsli']);

        $prestasi = $response->viewData('prestasi');
        $this->assertSame('Lomba Web Nasional 2026', $prestasi['nama']);
    }
}
