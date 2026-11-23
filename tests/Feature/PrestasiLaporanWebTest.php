<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Laporan\LaporanPrestasi;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Laporan PDF/Excel halaman Prestasi (admin dan kaprodi) lewat API /admin/prestasi/rekap.
 * Jawaban API ditiru dengan Http::fake, jadi tes ini tidak butuh API hidup dan tidak menyentuh database.
 */
class PrestasiLaporanWebTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['api_token' => 'token-uji']);
    }

    private function masuk(int $idRole, string $namaRole): static
    {
        $user = (new User)->forceFill(['id_user' => $idRole, 'id_role' => $idRole, 'email' => $namaRole.'@test.local', 'status' => true]);
        $user->setRelation('role', (new Role)->forceFill(['id_role' => $idRole, 'nama_role' => $namaRole]));

        return $this->actingAs($user);
    }

    private function admin(): static
    {
        return $this->masuk(1, 'admin');
    }

    private function kaprodi(): static
    {
        return $this->masuk(2, 'kaprodi');
    }

    private function baris(array $ganti = []): array
    {
        return array_replace([
            'id' => 7,
            'nama' => 'Juara 1 Lomba Web Nasional',
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

    private function rekap(array $baris, int $halaman = 1, int $terakhir = 1): array
    {
        return [
            'success' => true,
            'message' => 'Rekap prestasi berhasil diambil',
            'data' => $baris,
            'meta' => ['current_page' => $halaman, 'per_page' => 200, 'total' => count($baris), 'last_page' => $terakhir],
        ];
    }

    public function test_guests_are_sent_to_login_and_wrong_role_is_refused(): void
    {
        Http::fake(['*/admin/prestasi/rekap*' => Http::response($this->rekap([]))]);

        $this->get(route('admin.prestasi.semua.laporan'))->assertRedirect(route('login'));
        $this->get(route('kaprodi.prestasi.semua.laporan'))->assertRedirect(route('login'));

        $this->kaprodi()->get(route('admin.prestasi.semua.laporan'))->assertForbidden();
        $this->admin()->get(route('kaprodi.prestasi.semua.laporan'))->assertForbidden();
    }

    public function test_admin_downloads_pdf_for_all_types(): void
    {
        Http::fake(['*/admin/prestasi/rekap*' => Http::response($this->rekap([
            $this->baris(),
            $this->baris(['id' => 8, 'nama' => 'Hibah Riset', 'tipe' => 'Dosen', 'pemilik_prestasi' => 'Wiktasari']),
        ]))]);

        $pdf = $this->admin()->get(route('admin.prestasi.semua.laporan', ['dari' => 2026, 'sampai' => 2026, 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringContainsString('laporan-prestasi-semua-2026.pdf', (string) $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer token-uji')
            && str_contains($r->url(), 'per_page=200')
            && ! str_contains($r->url(), 'jenis='));
    }

    public function test_kaprodi_downloads_excel_for_one_type_and_sends_jenis_to_api(): void
    {
        Http::fake(['*/admin/prestasi/rekap*' => Http::response($this->rekap([$this->baris(['tipe' => 'Dosen'])]))]);

        $this->kaprodi()->get(route('kaprodi.prestasi.dosen.laporan', ['dari' => 2026, 'sampai' => 2026, 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'jenis=dosen') && str_contains($r->url(), 'per_page=200'));
    }

    public function test_year_range_is_used_in_file_name(): void
    {
        Http::fake(['*/admin/prestasi/rekap*' => Http::response($this->rekap([
            $this->baris(['tahun' => 2025, 'raw_date' => '2025-03-01']),
            $this->baris(['id' => 8]),
        ]))]);

        $res = $this->admin()->get(route('admin.prestasi.mahasiswa.laporan', ['dari' => 2025, 'sampai' => 2026, 'format' => 'pdf']))->assertOk();

        $this->assertStringContainsString('laporan-prestasi-mahasiswa-2025-2026.pdf', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_report_fetches_every_page(): void
    {
        Http::fake(fn (Request $r) => str_contains($r->url(), 'page=2')
            ? Http::response($this->rekap([$this->baris(['id' => 2])], 2, 2))
            : Http::response($this->rekap([$this->baris(['id' => 1])], 1, 2)));

        $this->admin()->get(route('admin.prestasi.semua.laporan', ['format' => 'pdf']))->assertOk();

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'page=2'));
    }

    public function test_report_with_no_data_still_downloads(): void
    {
        Http::fake(['*/admin/prestasi/rekap*' => Http::response($this->rekap([]))]);

        $this->admin()->get(route('admin.prestasi.dosen.laporan', ['format' => 'pdf']))->assertOk();
    }

    public function test_report_goes_back_with_a_message_when_api_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('mati'));

        $this->admin()->get(route('admin.prestasi.semua.laporan'))
            ->assertRedirect(route('admin.prestasi'))
            ->assertSessionHas('error');

        $this->kaprodi()->get(route('kaprodi.prestasi.semua.laporan'))
            ->assertRedirect(route('kaprodi.prestasi'))
            ->assertSessionHas('error');
    }

    public function test_susun_filters_by_year_and_matches_column_count(): void
    {
        $r = fn (array $g = []) => array_replace([
            'nama' => 'A', 'kategori' => 'Lomba', 'tipe' => 'Dosen', 'pemilik' => 'X', 'tingkat' => '', 'capaian' => '',
            'tanggal' => '1 Mei 2026', 'tanggal_iso' => '2026-05-01', 'tahun' => 2026, 'semester' => 'Genap',
        ], $g);

        $rows = [$r(), $r(['nama' => 'B', 'tahun' => 2025, 'tanggal_iso' => '2025-05-01'])];

        $semua = LaporanPrestasi::susun('semua', $rows, 2026, 2026);
        $dosen = LaporanPrestasi::susun('dosen', $rows, 2025, 2026);

        $this->assertSame(1, $semua['jumlah']);
        $this->assertCount(9, $semua['kolom']);
        $this->assertCount(9, $semua['data'][0]);
        $this->assertSame(2, $dosen['jumlah']);
        $this->assertCount(8, $dosen['kolom']);
        $this->assertCount(8, $dosen['data'][0]);
        $this->assertSame('-', $dosen['data'][0][4]); // tingkat kosong tampil "-"
    }
}
