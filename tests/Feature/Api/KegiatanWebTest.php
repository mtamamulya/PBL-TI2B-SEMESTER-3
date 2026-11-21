<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Halaman web Kegiatan (daftar, detail, hapus, laporan) yang memakai API lewat ApiClient.
 * Jawaban API ditiru dengan Http::fake, jadi tes ini tidak butuh API hidup dan tidak menyentuh database.
 * Login memakai user tiruan (tidak disimpan ke database) dengan role terpasang, jadi AdminOnly / KaprodiOnly ikut berjalan.
 */
class KegiatanWebTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withSession(['api_token' => 'token-uji']);
    }

    /** User tiruan: tidak disimpan ke database, role dipasang langsung agar tidak ada query. */
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
            'id' => '12',
            'jenis' => 'dosen',
            'nama' => 'Seminar Nasional Kecerdasan Buatan',
            'id_kategori' => 3,
            'kategori' => 'Seminar',
            'pengaju' => 'Wiktasari',
            'tanggal' => '5 Oktober 2026',
            'raw_date' => '2026-10-05',
            'tahun' => 2026,
            'status' => 'Disetujui',
            'semester' => 'Ganjil',
            'tingkat' => '',
            'kelas' => '',
        ], $ganti);
    }

    private function daftar(array $baris, int $halaman = 1, int $terakhir = 1): array
    {
        return [
            'success' => true,
            'message' => 'Daftar kegiatan berhasil diambil',
            'data' => $baris,
            'meta' => ['current_page' => $halaman, 'per_page' => 200, 'total' => count($baris), 'last_page' => $terakhir],
        ];
    }

    private function detail(array $ganti = []): array
    {
        return ['success' => true, 'message' => 'Data berhasil diambil', 'data' => $this->baris([
            'nomor_sk' => '123/SK/2026',
            'tanggal_sk' => '2026-10-01',
            'isian' => [
                ['input' => 'penyelenggara', 'isian' => 'Polines'],
                ['input' => 'tanggal_mulai', 'isian' => '2026-10-05'],
            ],
        ] + $ganti)];
    }

    // ---------- akses ----------

    public function test_guests_are_sent_to_login_and_wrong_role_is_refused(): void
    {
        Http::fake(['*/admin/kegiatan*' => Http::response($this->daftar([]))]);

        $this->get(route('admin.kegiatan.index'))->assertRedirect(route('login'));
        $this->get(route('kaprodi.kegiatan.index'))->assertRedirect(route('login'));

        $this->kaprodi()->get(route('admin.kegiatan.index'))->assertForbidden();
        $this->admin()->get(route('kaprodi.kegiatan.index'))->assertForbidden();
    }

    // ---------- daftar ----------

    public function test_list_splits_rows_into_tabs_by_jenis(): void
    {
        Http::fake(['*/admin/kegiatan*' => Http::response($this->daftar([
            $this->baris(['id' => '12', 'jenis' => 'dosen']),
            $this->baris(['id' => '9', 'jenis' => 'mahasiswa', 'nama' => 'Hackathon', 'raw_date' => '2026-03-20']),
            $this->baris(['id' => '4', 'jenis' => 'prodi', 'nama' => 'Workshop', 'raw_date' => '2026-01-15']),
        ]))]);

        $this->admin()->get(route('admin.kegiatan.index'))
            ->assertOk()
            ->assertViewHas('data', fn ($d) => count($d['semua']) === 3
                && count($d['dosen']) === 1 && count($d['mahasiswa']) === 1 && count($d['prodi']) === 1
                && $d['semua'][0]['nama'] === 'Seminar Nasional Kecerdasan Buatan') // terbaru di atas
            ->assertViewHas('tahun', fn ($t) => $t['semua'] === [2026])
            ->assertViewHas('apiError', null);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer token-uji'));
    }

    public function test_kaprodi_sees_the_same_list(): void
    {
        Http::fake(['*/admin/kegiatan*' => Http::response($this->daftar([$this->baris()]))]);

        $this->kaprodi()->get(route('kaprodi.kegiatan.index'))
            ->assertOk()
            ->assertViewHas('data', fn ($d) => count($d['semua']) === 1 && count($d['dosen']) === 1);
    }

    public function test_list_fetches_every_page(): void
    {
        Http::fake(fn (Request $r) => str_contains($r->url(), 'page=2')
            ? Http::response($this->daftar([$this->baris(['id' => '2', 'jenis' => 'mahasiswa'])], 2, 2))
            : Http::response($this->daftar([$this->baris(['id' => '1'])], 1, 2)));

        $this->admin()->get(route('admin.kegiatan.index'))
            ->assertOk()
            ->assertViewHas('data', fn ($d) => count($d['semua']) === 2);
    }

    public function test_list_still_opens_with_an_error_message_when_api_is_down(): void
    {
        Http::fake(fn () => throw new ConnectionException('mati'));

        $this->admin()->get(route('admin.kegiatan.index'))
            ->assertOk()
            ->assertViewHas('data', fn ($d) => $d['semua'] === [])
            ->assertViewHas('apiError', fn ($e) => str_contains((string) $e, '503'));
    }

    public function test_expired_token_redirects_to_login(): void
    {
        Http::fake(['*/admin/kegiatan*' => Http::response(['success' => false, 'message' => 'Belum login atau token tidak valid.', 'errors' => []], 401)]);

        $this->admin()->get(route('admin.kegiatan.index'))->assertRedirect(route('login'));
    }

    // ---------- detail ----------

    public function test_admin_detail_shows_extra_information_from_api(): void
    {
        Http::fake(['*/admin/kegiatan/12' => Http::response($this->detail())]);

        $this->admin()->get(route('admin.kegiatan.dosen.detail.show', 12))
            ->assertOk()
            ->assertViewHas('d', fn ($d) => $d['judul'] === 'Seminar Nasional Kecerdasan Buatan'
                && $d['pengaju'] === 'Wiktasari'
                && $d['penyelenggara'] === 'Polines'
                && $d['tambahan']['Nomor SK'] === '123/SK/2026'
                && $d['tambahan']['Tanggal mulai'] === '2026-10-05');
    }

    public function test_detail_uses_members_documents_and_history_when_api_sends_them(): void
    {
        Http::fake(['*/admin/kegiatan/12' => Http::response($this->detail([
            'anggota' => [['nama' => 'Wiktasari', 'status' => 'Pengaju'], ['nama' => 'Mukhlis', 'status' => 'Anggota']],
            'dokumen' => [['nama' => 'Surat Tugas.pdf', 'kategori' => 'Surat Tugas', 'url' => 'https://contoh.test/a.pdf']],
            'riwayat' => [['status' => 'Diajukan', 'tanggal' => '1 Oktober 2026']],
        ]))]);

        $this->admin()->get(route('admin.kegiatan.dosen.detail.show', 12))
            ->assertOk()
            ->assertViewHas('d', fn ($d) => count($d['anggota']) === 2
                && $d['dokumen'][0]['url'] === 'https://contoh.test/a.pdf'
                && $d['riwayat'] === ['Diajukan — 1 Oktober 2026']);
    }

    public function test_kaprodi_detail_of_a_student_activity(): void
    {
        Http::fake(['*/admin/kegiatan/9' => Http::response($this->detail(['id' => '9', 'jenis' => 'mahasiswa', 'nama' => 'Hackathon']))]);

        $this->kaprodi()->get(route('kaprodi.kegiatan.mahasiswa.show', 9))
            ->assertOk()
            ->assertViewHas('d', fn ($d) => $d['judul'] === 'Hackathon' && $d['jenis'] === 'Kegiatan mahasiswa');
    }

    public function test_detail_is_404_when_api_says_so(): void
    {
        Http::fake(['*/admin/kegiatan/999' => Http::response(['success' => false, 'message' => 'Kegiatan tidak ditemukan.', 'errors' => []], 404)]);

        $this->admin()->get(route('admin.kegiatan.dosen.detail.show', 999))->assertNotFound();
    }

    public function test_detail_of_another_jenis_is_404(): void
    {
        Http::fake(['*/admin/kegiatan/9' => Http::response($this->detail(['id' => '9', 'jenis' => 'mahasiswa']))]);

        $this->admin()->get(route('admin.kegiatan.dosen.detail.show', 9))->assertNotFound();
    }

    // ---------- hapus ----------

    public function test_admin_can_delete_an_activity_through_the_api(): void
    {
        Http::fake(['*/admin/kegiatan/12' => Http::response(['success' => true, 'message' => 'Kegiatan berhasil dihapus.', 'data' => null])]);

        $this->admin()->delete(route('admin.kegiatan.dosen.destroy', 12))
            ->assertRedirect(route('admin.kegiatan.index', ['tab' => 'dosen']))
            ->assertSessionHas('success', 'Kegiatan berhasil dihapus.');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/admin/kegiatan/12'));
    }

    public function test_kaprodi_delete_is_refused_by_the_api_with_a_clear_message(): void
    {
        Http::fake(['*/admin/kegiatan/12' => Http::response(['success' => false, 'message' => 'Hanya admin yang dapat menghapus kegiatan.', 'errors' => []], 403)]);

        $this->kaprodi()->delete(route('kaprodi.kegiatan.dosen.destroy', 12))
            ->assertRedirect(route('kaprodi.kegiatan.index', ['tab' => 'dosen']))
            ->assertSessionHas('error', 'Anda tidak memiliki izin untuk menghapus kegiatan.');
    }

    public function test_delete_reports_missing_and_still_used_activities(): void
    {
        Http::fake(['*/admin/kegiatan/404' => Http::response(['success' => false, 'message' => 'Kegiatan tidak ditemukan.', 'errors' => []], 404)]);
        $this->admin()->delete(route('admin.kegiatan.mahasiswa.destroy', 404))
            ->assertRedirect(route('admin.kegiatan.index', ['tab' => 'mahasiswa']))
            ->assertSessionHas('error', 'Kegiatan tidak ditemukan atau sudah dihapus.');

        Http::fake(['*/admin/kegiatan/5' => Http::response(['success' => false, 'message' => 'Kegiatan masih dipakai data lain (misalnya prestasi), hapus data tersebut dulu.', 'errors' => []], 409)]);
        $this->admin()->delete(route('admin.kegiatan.dosen.destroy', 5))
            ->assertSessionHas('error', 'Kegiatan masih dipakai data lain (misalnya prestasi), hapus data tersebut dulu.');
    }

    // ---------- laporan ----------

    public function test_report_pdf_and_excel_use_api_data(): void
    {
        Http::fake(['*/admin/kegiatan*' => Http::response($this->daftar([
            $this->baris(['id' => '12', 'jenis' => 'dosen']),
            $this->baris(['id' => '4', 'jenis' => 'prodi', 'nama' => 'Workshop', 'raw_date' => '2026-01-15']),
        ]))]);

        $pdf = $this->admin()->get(route('admin.kegiatan.semua.laporan', ['dari' => 2026, 'sampai' => 2026, 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('laporan-kegiatan-semua-2026.pdf', (string) $pdf->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->kaprodi()->get(route('kaprodi.kegiatan.dosen.laporan', ['dari' => 2026, 'sampai' => 2026, 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Data laporan berasal dari API (per jenis bila bukan "semua")
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'jenis=dosen') && str_contains($r->url(), 'per_page=200'));
    }

    public function test_report_with_no_data_still_downloads(): void
    {
        Http::fake(['*/admin/kegiatan*' => Http::response($this->daftar([]))]);

        $this->admin()->get(route('admin.kegiatan.prodi.laporan', ['format' => 'pdf']))->assertOk();
    }

    public function test_report_goes_back_with_a_message_when_api_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('mati'));

        $this->admin()->get(route('admin.kegiatan.semua.laporan'))
            ->assertRedirect(route('admin.kegiatan.index'))
            ->assertSessionHas('error');
    }

    // ---------- tambah kegiatan ----------

    private function form(array $ganti = []): array
    {
        return array_replace([
            'id_kategori' => 3,
            'jenis_kegiatan' => 'Kegiatan Prodi',
            'nama_kegiatan' => 'Workshop Kurikulum OBE',
            'penyelenggara' => 'Prodi TRK',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-06',
            'anggota' => ['Dosen:1', 'Mahasiswa:5'],
        ], $ganti);
    }

    private function hasilTambah(string $jenis = 'prodi', array $peringatan = []): array
    {
        return [
            'success' => true,
            'message' => 'Kegiatan berhasil ditambahkan.',
            'data' => ['id' => '21', 'jenis' => $jenis, 'nama' => 'Workshop Kurikulum OBE'],
            'peringatan' => $peringatan,
        ];
    }

    public function test_admin_store_posts_to_api_and_goes_to_the_tab_of_the_result(): void
    {
        Http::fake(['*/admin/kegiatan' => Http::response($this->hasilTambah('prodi'), 201)]);

        $this->admin()->post(route('admin.kegiatan.store'), $this->form())
            ->assertRedirect(route('admin.kegiatan.index', ['tab' => 'prodi']))
            ->assertSessionHas('success', 'Kegiatan berhasil ditambahkan.')
            ->assertSessionMissing('peringatan');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/admin/kegiatan')
            && $r->hasHeader('Authorization', 'Bearer token-uji')
            && $r['id_kategori'] == 3
            && $r['jenis_kegiatan'] === 'Kegiatan Prodi'
            && $r['anggota[0]'] === 'Dosen:1'
            && $r['anggota[1]'] === 'Mahasiswa:5');
    }

    public function test_kaprodi_store_redirects_to_kaprodi_list_with_tab_from_api(): void
    {
        // Tab mengikuti jenis dari API (pemilik kegiatan), bukan tebakan dari form.
        Http::fake(['*/admin/kegiatan' => Http::response($this->hasilTambah('dosen'), 201)]);

        $this->kaprodi()->post(route('kaprodi.kegiatan.store'), $this->form(['jenis_kegiatan' => 'Kegiatan Dosen']))
            ->assertRedirect(route('kaprodi.kegiatan.index', ['tab' => 'dosen']));
    }

    public function test_store_shows_warnings_from_api(): void
    {
        $peringatan = ['Kategori "Seminar" belum punya isian "tanggal_selesai", nilainya tidak disimpan.'];
        Http::fake(['*/admin/kegiatan' => Http::response($this->hasilTambah('prodi', $peringatan), 201)]);

        $this->admin()->post(route('admin.kegiatan.store'), $this->form())
            ->assertRedirect(route('admin.kegiatan.index', ['tab' => 'prodi']))
            ->assertSessionHas('peringatan', $peringatan);
    }

    public function test_store_sends_documents_with_bracket_keys_and_skips_empty_cards(): void
    {
        Http::fake(['*/admin/kegiatan' => Http::response($this->hasilTambah(), 201)]);

        $this->admin()->post(route('admin.kegiatan.store'), $this->form([
            'dokumen' => [
                0 => ['nama' => '', 'jenis' => '', 'keterangan' => '', 'tautan' => ''], // kartu kosong
                1 => ['nama' => 'Surat tugas', 'jenis' => 'Surat tugas', 'file' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')],
            ],
        ]))->assertRedirect(route('admin.kegiatan.index', ['tab' => 'prodi']));

        // Ada file, jadi dikirim multipart; kartu kosong dilewati.
        Http::assertSent(fn (Request $r) => $r->isMultipart() && str_ends_with($r->url(), '/admin/kegiatan'));
    }

    public function test_store_returns_to_the_form_with_errors_when_api_says_422(): void
    {
        Http::fake(['*/admin/kegiatan' => Http::response([
            'success' => false,
            'message' => 'Data yang dikirim tidak valid. Periksa kembali isian Anda.',
            'errors' => ['anggota' => ['Kegiatan dosen harus memiliki minimal satu anggota dosen.']],
        ], 422)]);

        $this->admin()->from(route('admin.kegiatan.create'))
            ->post(route('admin.kegiatan.store'), $this->form(['jenis_kegiatan' => 'Kegiatan Dosen', 'anggota' => ['Mahasiswa:5']]))
            ->assertRedirect(route('admin.kegiatan.create'))
            ->assertSessionHasErrors('anggota');
    }

    public function test_store_does_not_hide_api_downtime(): void
    {
        Http::fake(fn () => throw new ConnectionException('mati'));

        $this->admin()->post(route('admin.kegiatan.store'), $this->form())->assertStatus(503);
    }

    public function test_store_validates_locally_before_calling_api(): void
    {
        Http::fake();

        $this->admin()->from(route('admin.kegiatan.create'))
            ->post(route('admin.kegiatan.store'), $this->form(['anggota' => [], 'id_kategori' => '']))
            ->assertRedirect(route('admin.kegiatan.create'))
            ->assertSessionHasErrors(['anggota', 'id_kategori']);

        Http::assertNothingSent();
    }
}