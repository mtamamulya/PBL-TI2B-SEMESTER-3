<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
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

    private function barisRekap(array $ganti = []): array
    {
        return array_replace([
            'id' => 1,
            'nama' => 'Lomba Web Nasional 2026',
            'kategori' => 'Lomba',
            'pemilik_prestasi' => 'Darryll Marchiand',
            'tipe' => 'Mahasiswa',
            'tingkat' => 'Nasional',
            'capaian' => 'Juara 1',
            'tanggal' => '17 September 2026',
            'raw_date' => '2026-09-17',
            'tahun' => 2026,
            'semester' => 'Ganjil',
        ], $ganti);
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
            // Daftar: API rekap (admin dan kaprodi), bukan /prestasi publik.
            '*/admin/prestasi/rekap*' => Http::response([
                'success' => true,
                'message' => 'Rekap prestasi berhasil diambil',
                'data' => [$this->barisRekap()],
                'meta' => ['current_page' => 1, 'per_page' => 200, 'total' => 1, 'last_page' => 1],
            ], 200),
            // Detail tetap dari /prestasi/{id}.
            '*/prestasi/1' => Http::response([
                'success' => true,
                'message' => 'Data berhasil diambil',
                'data' => $itemPrestasi,
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
        $this->assertSame('Lomba', $rows[0]['kategori']);
        $this->assertSame('Darryll Marchiand', $rows[0]['pengunggah']);
        $this->assertSame('Mahasiswa', $rows[0]['tipe']);
        $this->assertSame('Ganjil', $rows[0]['semester']);
    }

    public function test_kaprodi_list_fetches_every_page(): void
    {
        Http::fake(fn (Request $r) => str_contains($r->url(), 'page=2')
            ? Http::response(['success' => true, 'data' => [$this->barisRekap(['id' => 2, 'tipe' => 'Dosen'])], 'meta' => ['last_page' => 2]])
            : Http::response(['success' => true, 'data' => [$this->barisRekap(['id' => 1])], 'meta' => ['last_page' => 2]]));

        $response = $this->actingAs($this->masukSebagaiKaprodi())
            ->withSession(['kaprodi' => ['nama' => 'Kaprodi']])
            ->get(route('kaprodi.prestasi'));

        $response->assertOk();
        $this->assertCount(2, $response->viewData('rows'));
        $this->assertSame('Dosen', $response->viewData('rows')[1]['tipe']);
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
