<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengajuanFormTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

    private int $idJenisMahasiswa;

    private int $idJenisDosen;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->userId = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'pengajuan-student@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->idJenisMahasiswa = $this->insertJenisKegiatan('Kegiatan Mahasiswa');
        $this->idJenisDosen = $this->insertJenisKegiatan('Kegiatan Dosen');

        foreach (['Lomba', 'Volunteer', 'Magang', 'Pengabdian'] as $namaKategori) {
            $idKategori = DB::table('kategori')->insertGetId([
                'id_jenis_kegiatan' => $this->idJenisMahasiswa,
                'nama_kategori' => $namaKategori,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('isian_kategori')->insert([
                [
                    'id_kategori' => $idKategori,
                    'input' => 'Penyelenggara',
                    'jenis_input' => 'teks',
                    'aturan' => 'NOT NULL',
                    'jumlah_isian' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id_kategori' => $idKategori,
                    'input' => 'Lokasi',
                    'jenis_input' => 'teks',
                    'aturan' => 'NOT NULL',
                    'jumlah_isian' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        DB::table('kategori')->insert([
            'id_jenis_kegiatan' => $this->idJenisDosen,
            'nama_kategori' => 'Penelitian Dosen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::findOrFail($this->userId));
    }

    private function insertJenisKegiatan(string $nama): int
    {
        $jenisCols = Schema::getColumnListing('jenis_kegiatan');
        $payload = ['created_at' => now(), 'updated_at' => now()];
        foreach ($jenisCols as $col) {
            if (! in_array($col, ['id', 'id_jenis_kegiatan', 'created_at', 'updated_at'], true)) {
                $payload[$col] = $nama;
            }
        }

        return DB::table('jenis_kegiatan')->insertGetId($payload);
    }

    private function createKegiatanInDb(string $title, string $status = 'diajukan', string $category = 'Pengabdian'): int
    {
        $idKategori = DB::table('kategori')
            ->where('nama_kategori', $category)
            ->value('id_kategori')
            ?? DB::table('kategori')->value('id_kategori');

        $idKegiatan = DB::table('kegiatan')->insertGetId([
            'id_kategori' => $idKategori,
            'id_user' => $this->userId,
            'nama_kegiatan' => $title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('timeline')->insert([
            'id_kegiatan' => $idKegiatan,
            'status' => $status,
            'tanggal_status' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $idKegiatan;
    }

    private function loginDosen(): void
    {
        $role = DB::table('roles')->insertGetId(['nama_role' => 'dosen'], 'id_role');
        $this->actingAs(User::factory()->create(['id_role' => $role, 'status' => true]));
    }

    public function test_new_forms_do_not_prefill_team_members(): void
    {
        foreach ([route('pengajuan.create'), route('pengajuan.dosen.create')] as $url) {
            if ($url === route('pengajuan.dosen.create')) {
                $this->loginDosen();
            }
            $this->get($url)
                ->assertOk()
                ->assertDontSee('Ivan Sigit Santoso')
                ->assertDontSee('Anisa Aulia Nurusyifa');
        }
    }

    public function test_dynamic_form_filters_categories_by_role(): void
    {
        $this->get(route('pengajuan.create'))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.create')
            ->assertSee('Lomba')
            ->assertSee('Pengabdian')
            ->assertDontSee('Penelitian Dosen');

        $this->loginDosen();
        $this->get(route('pengajuan.dosen.create'))
            ->assertOk()
            ->assertViewIs('dosen.pengajuan.create')
            ->assertViewHas('categoriesData', fn ($cats) => $cats->pluck('nama_kategori')->contains('Penelitian Dosen')
                && ! $cats->pluck('nama_kategori')->contains('Pengabdian'));
    }

    public function test_dynamic_submission_persists_details_and_rejects_wrong_role_category(): void
    {
        Storage::fake('public');

        $idKategoriMhs = DB::table('kategori')->where('nama_kategori', 'Lomba')->value('id_kategori');
        $idKategoriDosen = DB::table('kategori')->where('nama_kategori', 'Penelitian Dosen')->value('id_kategori');
        $isianIds = DB::table('isian_kategori')
            ->where('id_kategori', $idKategoriMhs)
            ->pluck('id_isian_kategori', 'input');

        $idIsianBerkas = DB::table('isian_kategori')->insertGetId([
            'id_kategori' => $idKategoriMhs,
            'input' => 'Sertifikat Lomba',
            'jenis_input' => 'berkas',
            'aturan' => 'NULL',
            'jumlah_isian' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post(route('pengajuan.store'), [
            'id_kategori' => $idKategoriMhs,
            'title' => 'Lomba Inovasi Mahasiswa',
            'detail' => [
                $isianIds['Penyelenggara'] => 'Kemendiktisaintek',
                $isianIds['Lokasi'] => 'Semarang',
            ],
            'detail_files' => [
                $idIsianBerkas => UploadedFile::fake()->create('sertifikat.pdf', 100, 'application/pdf'),
            ],
        ]);

        $newId = DB::table('kegiatan')->where('nama_kegiatan', 'Lomba Inovasi Mahasiswa')->value('id_kegiatan');
        $this->assertNotNull($newId);
        $response->assertRedirect(route('pengajuan.show', $newId));

        $this->assertDatabaseHas('timeline', [
            'id_kegiatan' => $newId,
            'status' => 'diajukan',
        ]);
        $this->assertDatabaseHas('detail_kegiatan', [
            'id_kegiatan' => $newId,
            'id_isian_kategori' => $isianIds['Lokasi'],
            'isian' => 'Semarang',
        ]);

        $this->get(route('pengajuan.show', $newId))
            ->assertOk()
            ->assertSee('Lomba Inovasi Mahasiswa')
            ->assertSee('Kemendiktisaintek')
            ->assertSee('Semarang');

        $this->from(route('pengajuan.create'))
            ->post(route('pengajuan.store'), [
                'id_kategori' => $idKategoriDosen,
                'title' => 'Kategori Dosen di Form Mahasiswa',
            ])
            ->assertRedirect(route('pengajuan.create'))
            ->assertSessionHasErrors('id_kategori');
    }

    public function test_dynamic_submission_can_be_edited_and_updated(): void
    {
        $idKategori = DB::table('kategori')->where('nama_kategori', 'Magang')->value('id_kategori');
        $isianIds = DB::table('isian_kategori')
            ->where('id_kategori', $idKategori)
            ->pluck('id_isian_kategori', 'input');

        $this->post(route('pengajuan.store'), [
            'id_kategori' => $idKategori,
            'title' => 'Magang Awal',
            'detail' => [
                $isianIds['Penyelenggara'] => 'PT Awal',
                $isianIds['Lokasi'] => 'Semarang',
            ],
        ]);

        $activityId = DB::table('kegiatan')->where('nama_kegiatan', 'Magang Awal')->value('id_kegiatan');
        $this->assertNotNull($activityId);

        $this->get(route('pengajuan.edit', $activityId))
            ->assertOk()
            ->assertSee('Magang Awal');

        $this->put(route('pengajuan.update', $activityId), [
            'id_kategori' => $idKategori,
            'title' => 'Magang Diperbarui',
            'detail' => [
                $isianIds['Penyelenggara'] => 'PT Baru',
                $isianIds['Lokasi'] => 'Yogyakarta',
            ],
        ])->assertRedirect(route('pengajuan.show', $activityId));

        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan' => $activityId,
            'nama_kegiatan' => 'Magang Diperbarui',
        ]);
        $this->assertDatabaseHas('detail_kegiatan', [
            'id_kegiatan' => $activityId,
            'id_isian_kategori' => $isianIds['Lokasi'],
            'isian' => 'Yogyakarta',
        ]);
    }

    public function test_new_submission_is_also_listed_as_an_activity(): void
    {
        $idKategori = DB::table('kategori')->where('nama_kategori', 'Pengabdian')->value('id_kategori');
        $isianIds = DB::table('isian_kategori')
            ->where('id_kategori', $idKategori)
            ->pluck('id_isian_kategori', 'input');

        $response = $this->post(route('pengajuan.store'), [
            'id_kategori' => $idKategori,
            'title' => 'Pengajuan yang menjadi kegiatan',
            'detail' => [
                $isianIds['Penyelenggara'] => 'Program Studi',
                $isianIds['Lokasi'] => 'Semarang',
            ],
        ]);

        $id = DB::table('kegiatan')->where('nama_kegiatan', 'Pengajuan yang menjadi kegiatan')->value('id_kegiatan');
        $this->assertNotNull($id);
        $response->assertRedirect(route('pengajuan.show', $id));

        $this->get(route('pengajuan.index'))
            ->assertOk()
            ->assertSee('Pengajuan yang menjadi kegiatan');

        $this->get(route('kegiatan.index'))
            ->assertOk()
            ->assertViewIs('mahasiswa.kegiatan.index')
            ->assertDontSee('Unduh Sertifikat');
    }

    public function test_activity_form_adds_directly_to_history_without_creating_an_approval_submission(): void
    {
        $idKategori = DB::table('kategori')->where('nama_kategori', 'Pengabdian')->value('id_kategori');
        $isianIds = DB::table('isian_kategori')
            ->where('id_kategori', $idKategori)
            ->pluck('id_isian_kategori', 'input');

        $this->get(route('kegiatan.create'))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.create')
            ->assertSee('Tambah Kegiatan')
            ->assertSee('Simpan ke Riwayat')
            ->assertSee(route('kegiatan.store'));

        $this->post(route('kegiatan.store'), [
            'id_kategori' => $idKategori,
            'title' => 'Kegiatan yang langsung dicatat',
            'detail' => [
                $isianIds['Penyelenggara'] => 'Program Studi',
                $isianIds['Lokasi'] => 'Semarang',
            ],
        ])->assertRedirect(route('kegiatan.index'));

        $idKegiatan = DB::table('kegiatan')
            ->where('nama_kegiatan', 'Kegiatan yang langsung dicatat')
            ->value('id_kegiatan');
        $this->assertNotNull($idKegiatan);
        $this->assertSame(0, DB::table('timeline')->where('id_kegiatan', $idKegiatan)->count());

        $this->get(route('kegiatan.index'))
            ->assertOk()
            ->assertSee('Kegiatan yang langsung dicatat');
    }

    public function test_activity_list_includes_activities_tagged_to_the_current_user(): void
    {
        $this->createKegiatanInDb('Lomba yang ditag', 'diajukan', 'Lomba');

        $this->get(route('pengajuan.index'))
            ->assertOk()
            ->assertSee('Lomba yang ditag');

        $response = $this->get(route('kegiatan.index'))
            ->assertOk()
            ->assertSee('Lomba yang ditag')
            ->assertDontSee('FKBSE 2025');

        $this->assertSame(1, $response->viewData('categoryCounts')['LOMBA']);
        $this->assertCount(1, $response->viewData('allActivities'));
    }

    public function test_student_dashboard_uses_activity_aggregates_for_active_and_completed_work(): void
    {
        $this->createKegiatanInDb('Pengajuan aktif', 'direview', 'Lomba');
        $this->createKegiatanInDb('Kegiatan selesai', 'selesai', 'Pengabdian');

        $response = $this->get(route('mahasiswa.index'))->assertOk();

        $this->assertSame(1, $response->viewData('ringkasan')['pengajuan_aktif']);
        $this->assertSame(0, $response->viewData('ringkasan')['dokumen_siap']);
        $this->assertCount(1, $response->viewData('kegiatanSelesai'));
    }

    public function test_dashboard_cards_and_recent_submissions_use_owned_database_records(): void
    {
        $ownedId = $this->createKegiatanInDb('Pengajuan milik dashboard', 'diajukan', 'Lomba');
        $otherUserId = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'dashboard-other-'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('kegiatan')->insert([
            'id_kategori' => DB::table('kategori')->where('nama_kategori', 'Lomba')->value('id_kategori'),
            'id_user' => $otherUserId,
            'nama_kegiatan' => 'Pengajuan milik orang lain',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get(route('mahasiswa.dashboard'))->assertOk();

        $this->assertSame(1, $response->viewData('totalPengajuan'));
        $this->assertSame(1, $response->viewData('totalKegiatan'));
        $this->assertSame(0, $response->viewData('totalPrestasi'));
        $this->assertSame([$ownedId], $response->viewData('pengajuanTerbaru')->pluck('id_kegiatan')->all());
        $response->assertSee('Pengajuan milik dashboard')
            ->assertDontSee('Pengajuan milik orang lain');
    }

    public function test_detail_page_does_not_show_surat_preview(): void
    {
        $id = $this->createKegiatanInDb('Kegiatan Uji Detail');

        $this->get(route('pengajuan.show', $id))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.show')
            ->assertSee('Hapus pengajuan')
            ->assertDontSee('Preview surat')
            ->assertDontSee('Preview surat pengajuan');
    }

    public function test_pengajuan_can_be_deleted_for_every_status(): void
    {
        $statuses = ['diajukan', 'direview', 'diterima', 'selesai', 'ditolak', 'direvisi'];
        $ids = [];

        foreach ($statuses as $status) {
            $ids[$status] = $this->createKegiatanInDb('Pengajuan status '.$status, $status);
        }

        foreach ($statuses as $status) {
            $id = $ids[$status];
            $this->get(route('pengajuan.show', $id))
                ->assertOk()
                ->assertSee('Pengajuan status '.$status)
                ->assertSee('Hapus pengajuan');

            $this->delete(route('pengajuan.destroy', $id))
                ->assertRedirect(route('pengajuan.index'))
                ->assertSessionHas('success', 'Pengajuan berhasil dihapus.');

            $this->get(route('pengajuan.show', $id))->assertNotFound();
        }

        $this->get(route('pengajuan.index'))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.index')
            ->assertDontSee('Pengajuan status diajukan');
    }

    public function test_existing_dosen_and_mahasiswa_pages_remain_available(): void
    {
        $this->get(route('pengajuan.dosen'))
            ->assertOk()
            ->assertViewIs('dosen.pengajuan.dosen')
            ->assertSee('<title>Pengajuan Dosen | DOSEN</title>', false)
            ->assertSee('Cari kegiatan atau nama dosen...')
            ->assertSee('/pengajuan/dosen')
            ->assertSee('${k.id}', false)
            ->assertDontSee('Seminar Nasional Transformasi Digital Kampus')
            ->assertDontSee('Tambah pengajuan')
            ->assertDontSee('Halaman sementara');

        $this->get('/mahasiswa/pengajuan-segera')->assertNotFound();
        $this->get('/pengajuan-segera')->assertNotFound();
    }

    public function test_dashboard_renders_with_develop_pengajuan_routes(): void
    {
        $id = $this->createKegiatanInDb('Pengabdian Uji Dashboard Terbaru', 'diajukan', 'Pengabdian');

        $response = $this->get(route('pengajuan.dashboard'));

        $response
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.dashboard')
            ->assertSee('TOTAL PENGAJUAN')
            ->assertSee('id="desktop-mahasiswa-submenu"', false)
            ->assertSee('id="mobile-pengajuan-nav"', false)
            ->assertSee('ti-calendar-event')
            ->assertSee('ti-news')
            ->assertSee('x-data="{ open: true }"', false)
            ->assertSee(config('mahasiswa.nama'))
            ->assertSee(config('mahasiswa.nim'))
            ->assertSee(now()->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i'))
            ->assertDontSee('12 Maret 2026')
            ->assertDontSee('Tambah Kegiatan')
            ->assertSee('Pilih kategori kegiatan')
            ->assertSee('Lengkapi data kegiatan')
            ->assertSee('Tambahkan tautan dokumen')
            ->assertSee('Periksa dan kirim pengajuan')
            ->assertSee('Pengabdian Uji Dashboard Terbaru')
            ->assertSee('Diajukan')
            ->assertSee('href="'.route('pengajuan.show', $id).'"', false)
            ->assertDontSee('proposal penelitian internal');
    }

    public function test_berita_pages_and_draft_workflow_are_available(): void
    {
        $this->get(route('pengajuan.berita.index'))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.berita.index')
            ->assertSee('Ajukan Berita')
            ->assertSee('Kegiatan Mahasiswa');
        $this->get(route('pengajuan.berita.create'))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.berita.form')
            ->assertSee('Dari kegiatan sebelumnya')
            ->assertSee('Tanpa kegiatan sebelumnya')
            ->assertSee('Kategori berita')
            ->assertSee('data-rich-text-editor', false)
            ->assertSee('Foto sampul berita')
            ->assertSee('Simpan Draft');

        $this->post(route('pengajuan.berita.store'), ['intent' => 'draft'])
            ->assertRedirect(route('pengajuan.berita.show', 1));
        $this->get(route('pengajuan.berita.show', 1))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.berita.show')
            ->assertSee('Draft');
    }

    public function test_berita_form_lists_existing_and_new_pengajuan_activities(): void
    {
        $this->createKegiatanInDb('Kegiatan milik mahasiswa aktif', 'selesai', 'Lomba');

        $this->get(route('pengajuan.berita.create'))
            ->assertOk()
            ->assertSee('Kegiatan milik mahasiswa aktif')
            ->assertDontSee('Pengajuan Lomba KMPIN VII 2026');

        $idKategori = DB::table('kategori')->where('nama_kategori', 'Pengabdian')->value('id_kategori');
        $isianIds = DB::table('isian_kategori')
            ->where('id_kategori', $idKategori)
            ->pluck('id_isian_kategori', 'input');

        $response = $this->post(route('pengajuan.store'), [
            'id_kategori' => $idKategori,
            'title' => 'Pengabdian Desa Digital',
            'detail' => [
                $isianIds['Penyelenggara'] => 'Program Studi',
                $isianIds['Lokasi'] => 'Semarang',
            ],
        ]);

        $newId = DB::table('kegiatan')->where('nama_kegiatan', 'Pengabdian Desa Digital')->value('id_kegiatan');
        $this->assertNotNull($newId);
        $response->assertRedirect(route('pengajuan.show', $newId));

        $this->get(route('pengajuan.berita.create'))
            ->assertOk()
            ->assertSee('Kegiatan milik mahasiswa aktif')
            ->assertSee('Pengabdian Desa Digital')
            ->assertDontSee('Lomba UI/UX Design Festival Mahasiswa 2026');
    }

    public function test_student_get_routes_do_not_expose_another_users_activities(): void
    {
        $this->createKegiatanInDb('Kegiatan privat pemilik');
        $otherUserId = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'other-'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::findOrFail($otherUserId))
            ->get(route('pengajuan.index'))
            ->assertOk()
            ->assertDontSee('Kegiatan privat pemilik')
            ->assertViewHas('all', fn ($activities) => $activities->isEmpty());

        $this->get(route('pengajuan.show', DB::table('kegiatan')->where('nama_kegiatan', 'Kegiatan privat pemilik')->value('id_kegiatan')))
            ->assertNotFound();
    }

    public function test_api_submission_uses_authenticated_owner_and_restricts_reads(): void
    {
        $otherUserId = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'api-other-'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idKategori = DB::table('kategori')->where('nama_kategori', 'Pengabdian')->value('id_kategori');

        $this->postJson('/api/v1/pengajuan-mahasiswa', [
            'id_kategori' => $idKategori,
            'id_user' => $otherUserId,
            'nama_kegiatan' => 'API milik akun login',
        ])->assertCreated()
            ->assertJsonPath('data.id_user', $this->userId);

        $idKegiatan = DB::table('kegiatan')
            ->where('nama_kegiatan', 'API milik akun login')
            ->value('id_kegiatan');
        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan' => $idKegiatan,
            'id_user' => $this->userId,
        ]);

        $this->actingAs(User::findOrFail($otherUserId))
            ->getJson('/api/v1/pengajuan-mahasiswa/'.$idKegiatan)
            ->assertNotFound();

        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/pengajuan-mahasiswa')->assertUnauthorized();
    }
}
