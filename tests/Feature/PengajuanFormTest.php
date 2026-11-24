<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PengajuanFormTest extends TestCase
{
    use RefreshDatabase;

    private int $userId;

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

        $jenisCols = Schema::getColumnListing('jenis_kegiatan');
        $jenisPayload = ['created_at' => now(), 'updated_at' => now()];
        foreach ($jenisCols as $col) {
            if (! in_array($col, ['id', 'id_jenis_kegiatan', 'created_at', 'updated_at'], true)) {
                $jenisPayload[$col] = 'Kegiatan Mahasiswa';
            }
        }
        $idJenis = DB::table('jenis_kegiatan')->insertGetId($jenisPayload);

        foreach (['Lomba', 'Volunteer', 'Magang', 'Pengabdian'] as $namaKategori) {
            DB::table('kategori')->insert([
                'id_jenis_kegiatan' => $idJenis,
                'nama_kategori' => $namaKategori,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs(User::findOrFail($this->userId));
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

    public function test_new_forms_do_not_prefill_team_members(): void
    {
        foreach ([route('pengajuan.create'), route('pengajuan.dosen.create')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('Ivan Sigit Santoso')
                ->assertDontSee('Anisa Aulia Nurusyifa');
        }
    }

    public function test_form_offers_requested_categories_and_link_document_types(): void
    {
        $response = $this->get(route('pengajuan.create'));

        $response->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.create')
            ->assertSee('Pengabdian')
            ->assertSee('Dokumentasi')
            ->assertSee('Surat Mitra')
            ->assertSee('Surat Mahasiswa')
            ->assertSee('Surat Pengabdian')
            ->assertSee('Tambah dokumen')
            ->assertDontSee('KKL')
            ->assertDontSee('Kunjungan Industri')
            ->assertDontSee('Lainnya')
            ->assertDontSee('Pilih berkas')
            ->assertDontSee('Preview surat');
    }

    public function test_lomba_forms_offer_course_instructors_and_reject_invalid_names(): void
    {
        foreach ([route('pengajuan.create'), route('pengajuan.dosen.create')] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('Cari nama dosen...')
                ->assertSee('Tambah')
                ->assertSee('Angga Wahyu Wibowo')
                ->assertSee('Kecerdasan Buatan')
                ->assertSee('Kuwat Santoso')
                ->assertSee('Jaringan Komputer')
                ->assertSee('Wiktasari')
                ->assertSee('Sistem Basis Data')
                ->assertSee('Tahan Prahara')
                ->assertSee('Internet of Things')
                ->assertSee('Prayitno')
                ->assertSee('Computer Vision')
                ->assertSee('Arif Setyawan')
                ->assertSee('Sistem Operasi');
        }

        $submission = [
            'category' => 'Lomba',
            'title' => 'Lomba Kampus',
            'participation' => 'Individu',
            'level' => 'Kabupaten',
            'organizer' => 'Panitia Kampus',
            'advisors' => ['Angga Wahyu Wibowo', 'Kuwat Santoso'],
            'location' => 'Semarang',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-31',
        ];

        $response = $this->post(route('pengajuan.store'), $submission);
        $newId = DB::table('kegiatan')->where('nama_kegiatan', 'Lomba Kampus')->value('id_kegiatan');
        $this->assertNotNull($newId);
        $response->assertRedirect(route('pengajuan.show', $newId));

        $submission['advisors'] = ['Angga Wahu Wibowo'];

        $this->from(route('pengajuan.create'))
            ->post(route('pengajuan.store'), $submission)
            ->assertRedirect(route('pengajuan.create'))
            ->assertSessionHasErrors('advisors.0');
    }

    public function test_each_category_accepts_link_documents(): void
    {
        $categories = [
            ['category' => 'Lomba', 'title' => 'Lomba Kampus', 'participation' => 'Individu', 'level' => 'Kabupaten', 'organizer' => 'Panitia Kampus'],
            ['category' => 'Volunteer', 'title' => 'Relawan Mengajar', 'organizer' => 'Komunitas', 'role' => 'Relawan'],
            ['category' => 'Magang', 'organizer' => 'Perusahaan', 'role' => 'Developer', 'duration_months' => 3],
            ['category' => 'Pengabdian', 'title' => 'Pengabdian Masyarakat', 'organizer' => 'Program Studi'],
        ];

        foreach ($categories as $category) {
            $expectedTitle = $category['category'] === 'Magang'
                ? 'Magang '.$category['role'].' di '.$category['organizer']
                : $category['title'];

            $response = $this->post(route('pengajuan.store'), array_merge([
                'location' => 'Semarang',
                'start_date' => '2026-10-30',
                'end_date' => '2026-10-31',
                'documents' => [[
                    'name' => 'Dokumen pendukung',
                    'type' => 'Surat Mitra',
                    'url' => 'https://example.com/dokumen',
                ]],
            ], $category));

            $id = DB::table('kegiatan')->where('nama_kegiatan', $expectedTitle)->value('id_kegiatan');
            $this->assertNotNull($id);

            $this->assertDatabaseHas('timeline', [
                'id_kegiatan' => $id,
                'status' => 'diajukan',
            ]);
            $this->assertDatabaseHas('dokumen', [
                'id_kegiatan' => $id,
                'nama_file' => 'Dokumen pendukung',
                'link_dokumen' => 'https://example.com/dokumen',
            ]);
            $this->assertSame(
                'Semarang',
                DB::table('detail_kegiatan')
                    ->join('isian_kategori', 'isian_kategori.id_isian_kategori', '=', 'detail_kegiatan.id_isian_kategori')
                    ->where('detail_kegiatan.id_kegiatan', $id)
                    ->where('isian_kategori.input', 'lokasi')
                    ->value('detail_kegiatan.isian')
            );

            $response->assertRedirect(route('pengajuan.show', $id));
            $this->get(route('pengajuan.show', $id))
                ->assertOk()
                ->assertSee($expectedTitle);
        }
    }

    public function test_magang_submission_preserves_the_optional_advisor(): void
    {
        $this->get(route('pengajuan.create'))
            ->assertOk()
            ->assertSee('Dosen Pendamping')
            ->assertSee('Masukkan nama dosen pendamping');

        $response = $this->post(route('pengajuan.store'), [
            'category' => 'Magang',
            'organizer' => 'Perusahaan',
            'role' => 'Developer',
            'duration_months' => 6,
            'advisor' => 'Kuwat Santoso',
            'location' => 'Semarang',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-31',
        ]);
        $activityId = DB::table('kegiatan')
            ->where('dosen_pendamping', 'Kuwat Santoso')
            ->orderByDesc('id_kegiatan')
            ->value('id_kegiatan');

        $this->assertNotNull($activityId);
        $response->assertRedirect(route('pengajuan.show', $activityId));
        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan' => $activityId,
            'dosen_pendamping' => 'Kuwat Santoso',
        ]);
    }

    public function test_database_magang_submission_persists_and_displays_the_advisor(): void
    {
        $response = $this->post(route('pengajuan.store'), [
            'category' => 'Magang',
            'organizer' => 'Perusahaan',
            'role' => 'Developer',
            'duration_months' => 6,
            'advisor' => 'Kuwat Santoso',
            'location' => 'Semarang',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-31',
        ]);
        $activityId = DB::table('kegiatan')
            ->where('dosen_pendamping', 'Kuwat Santoso')
            ->orderByDesc('id_kegiatan')
            ->value('id_kegiatan');

        $this->assertNotNull($activityId);
        $response->assertRedirect(route('pengajuan.show', $activityId));
        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan' => $activityId,
            'dosen_pendamping' => 'Kuwat Santoso',
        ]);

        $this->get(route('pengajuan.show', $activityId))
            ->assertOk()
            ->assertSee('Dosen Pendamping')
            ->assertSee('Kuwat Santoso');

        $this->get(route('pengajuan.edit', $activityId))
            ->assertOk()
            ->assertSee('value="Kuwat Santoso"', false);

        $this->put(route('pengajuan.update', $activityId), [
            'category' => 'Magang',
            'organizer' => 'Perusahaan',
            'role' => 'Developer',
            'duration_months' => 6,
            'advisor' => 'Tahan Prahara',
            'location' => 'Semarang',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-31',
        ])->assertRedirect(route('pengajuan.show', $activityId));

        $this->assertDatabaseHas('kegiatan', [
            'id_kegiatan' => $activityId,
            'dosen_pendamping' => 'Tahan Prahara',
        ]);
    }

    public function test_documents_are_optional_and_have_no_count_limit(): void
    {
        $submission = [
            'category' => 'Pengabdian',
            'title' => 'Pengabdian Kegiatan',
            'organizer' => 'Program Studi',
            'location' => 'Semarang',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-31',
        ];

        $response1 = $this->post(route('pengajuan.store'), $submission);
        $id1 = DB::table('kegiatan')->where('nama_kegiatan', 'Pengabdian Kegiatan')->value('id_kegiatan');
        $response1->assertRedirect(route('pengajuan.show', $id1));
        $this->get(route('pengajuan.show', $id1))
            ->assertOk()
            ->assertSee('Tidak ada dokumen pendukung.');

        $submission['title'] = 'Pengabdian Dengan Banyak Dokumen';
        $submission['documents'] = array_map(fn ($index) => [
            'name' => 'Dokumen '.$index,
            'type' => 'Dokumentasi',
            'url' => 'https://example.com/dokumen-'.$index,
        ], range(1, 7));

        $response2 = $this->post(route('pengajuan.store'), $submission);
        $id2 = DB::table('kegiatan')->where('nama_kegiatan', 'Pengabdian Dengan Banyak Dokumen')->value('id_kegiatan');
        $response2->assertRedirect(route('pengajuan.show', $id2));
        $this->get(route('pengajuan.show', $id2))
            ->assertOk()
            ->assertSee('Pengabdian Dengan Banyak Dokumen');
    }

    public function test_new_submission_is_also_listed_as_an_activity(): void
    {
        $response = $this->post(route('pengajuan.store'), [
            'category' => 'Pengabdian',
            'title' => 'Pengajuan yang menjadi kegiatan',
            'organizer' => 'Program Studi',
            'location' => 'Semarang',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-31',
            'documents' => [[
                'name' => 'Sertifikat Kegiatan',
                'type' => 'Dokumentasi',
                'url' => 'https://example.com/sertifikat',
            ]],
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
        $this->get(route('kegiatan.create'))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.create')
            ->assertSee('Tambah Kegiatan')
            ->assertSee('Simpan ke Riwayat')
            ->assertSee(route('kegiatan.store'));

        $this->post(route('kegiatan.store'), [
            'category' => 'Pengabdian',
            'title' => 'Kegiatan yang langsung dicatat',
            'organizer' => 'Program Studi',
            'location' => 'Semarang',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-31',
            'description' => 'Kegiatan langsung masuk ke riwayat database.',
            'documents' => [[
                'name' => 'Bukti kegiatan',
                'type' => 'Dokumentasi',
                'url' => 'https://example.com/bukti-kegiatan',
            ]],
        ])
            ->assertRedirect(route('kegiatan.index'));

        $idKegiatan = DB::table('kegiatan')
            ->where('nama_kegiatan', 'Kegiatan yang langsung dicatat')
            ->value('id_kegiatan');
        $details = DB::table('detail_kegiatan')
            ->join('isian_kategori', 'isian_kategori.id_isian_kategori', '=', 'detail_kegiatan.id_isian_kategori')
            ->where('detail_kegiatan.id_kegiatan', $idKegiatan)
            ->pluck('detail_kegiatan.isian', 'isian_kategori.input');

        $this->assertSame('Semarang', $details['lokasi']);
        $this->assertSame('2026-10-30', $details['tanggal_mulai']);
        $this->assertSame('Program Studi', $details['penyelenggara']);
        $this->assertSame('Kegiatan langsung masuk ke riwayat database.', $details['deskripsi']);
        $this->assertSame(0, DB::table('timeline')->where('id_kegiatan', $idKegiatan)->count());
        $this->assertDatabaseHas('dokumen', [
            'id_kegiatan' => $idKegiatan,
            'nama_file' => 'Bukti kegiatan',
            'link_dokumen' => 'https://example.com/bukti-kegiatan',
        ]);

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

        $this->withSession(['success' => 'Pengajuan berhasil dihapus.'])
            ->get(route('pengajuan.index'))
            ->assertOk()
            ->assertViewIs('mahasiswa.pengajuan.index')
            ->assertSee('Pengajuan berhasil dihapus.');
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

    public function test_teammate_mahasiswa_pages_are_available(): void
    {
        $mahasiswaResponse = $this->get(route('mahasiswa.dashboard'));
        $mahasiswaResponse
            ->assertOk()
            ->assertViewIs('mahasiswa.dashboard.dashboard')
            ->assertSee(config('mahasiswa.nama'))
            ->assertSee(config('mahasiswa.nim'))
            ->assertSee('Buat pengajuan dokumen baru')
            ->assertSee('href="'.route('pengajuan.index').'"', false)
            ->assertDontSee('Unduh KHS')
            ->assertSee('href="'.route('mahasiswa.dashboard').'"', false)
            ->assertSee('href="'.route('mahasiswa.index').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('aria-label="Buka atau tutup submenu Mahasiswa"', false)
            ->assertSee('Mahasiswa')
            ->assertSee('cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js', false)
            ->assertSee('cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.31.0/dist/tabler-icons.min.css', false)
            ->assertSee('Menu navigasi')
            ->assertSee('bg-white text-[#1f66e5]', false)
            ->assertSee('id="mobile-mahasiswa-submenu"', false)
            ->assertSee('href="'.route('mahasiswa.mata-kuliah').'"', false)
            ->assertSee('href="'.route('mahasiswa.prestasi').'"', false);
        $this->assertSame(1, preg_match('/<div id="mobile-mahasiswa-submenu"[^>]*>(.*?)<\/div>/s', $mahasiswaResponse->getContent(), $mobileMahasiswaSubmenu));
        $this->assertStringNotContainsString('Dashboard', $mobileMahasiswaSubmenu[1]);
        $this->assertSame(2, substr_count($mobileMahasiswaSubmenu[1], '<a '));
        foreach ([
            ['route' => 'mahasiswa.mata-kuliah', 'icon' => 'ti-book-2'],
            ['route' => 'mahasiswa.prestasi', 'icon' => 'ti-trophy'],
        ] as $submenuItem) {
            $submenuResponse = $this->get(route($submenuItem['route']));
            $submenuResponse
                ->assertOk()
                ->assertSee($submenuItem['icon'])
                ->assertSee(config('mahasiswa.nama'))
                ->assertSee('aria-current="page"', false);
            preg_match_all(
                '/<a\b(?=[^>]*href="'.preg_quote(route('mahasiswa.index'), '/').'")[^>]*class="([^"]+)"/',
                $submenuResponse->getContent(),
                $mahasiswaLinkClasses
            );
            $this->assertCount(2, $mahasiswaLinkClasses[1]);
            foreach ($mahasiswaLinkClasses[1] as $class) {
                $this->assertDoesNotMatchRegularExpression('/(?:^|\s)bg-white(?:\s|$)/', $class);
            }
        }
        $this->get(route('mahasiswa.index'))
            ->assertOk()
            ->assertViewIs('mahasiswa.mahasiswa.dashboard-mhs');
        $this->get(route('mahasiswa.mata-kuliah'))
            ->assertOk()
            ->assertViewIs('mahasiswa.mahasiswa.mata-kuliah');
        $this->get(route('mahasiswa.mata-kuliah', ['tab' => 'berjalan']))->assertOk();
        $this->get(route('mahasiswa.mata-kuliah', ['tab' => 'riwayat']))->assertOk();
        $this->get(route('mahasiswa.prestasi'))
            ->assertOk()
            ->assertViewIs('mahasiswa.mahasiswa.prestasi')
            ->assertSee('md:hidden', false)
            ->assertDontSee('Catat prestasi Anda dengan bukti yang lengkap');
        $this->get(route('mahasiswa.prestasi.tambah'))
            ->assertOk()
            ->assertViewIs('mahasiswa.mahasiswa.prestasi-tambah');
        $kegiatanResponse = $this->get(route('kegiatan.index'));
        $kegiatanResponse
            ->assertOk()
            ->assertSee('href="'.route('kegiatan.index').'"', false)
            ->assertSee('aria-current="page"', false);
        $this->assertDoesNotMatchRegularExpression(
            '/<a\b[^>]*href="'.preg_quote(route('mahasiswa.index'), '/').'"[^>]*aria-current="page"/s',
            $kegiatanResponse->getContent()
        );
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

    public function test_berita_rich_text_is_preserved_and_unsafe_markup_is_removed(): void
    {
        $this->post(route('pengajuan.berita.store'), [
            'intent' => 'draft',
            'content' => '<h2 onclick="alert(1)" style="color: rgb(200, 1, 2)">Judul</h2><p><strong>Rapi</strong><script>alert(1)</script><a href="javascript:alert(1)">tautan</a></p>',
        ])->assertRedirect(route('pengajuan.berita.show', 1));

        $this->get(route('pengajuan.berita.show', 1))
            ->assertOk()
            ->assertSee('<strong>Rapi</strong>', false)
            ->assertSee('Judul')
            ->assertSee('tautan')
            ->assertDontSee('onclick', false)
            ->assertDontSee('javascript:', false)
            ->assertDontSee('alert(1)');
    }

    public function test_berita_rich_text_character_limit_counts_text_content(): void
    {
        $this->from(route('pengajuan.berita.create'))
            ->post(route('pengajuan.berita.store'), [
                'intent' => 'draft',
                'content' => '<p>'.str_repeat('a', 5001).'</p>',
            ])
            ->assertSessionHasErrors('content');
    }

    public function test_berita_form_lists_existing_and_new_pengajuan_activities(): void
    {
        $this->createKegiatanInDb('Kegiatan milik mahasiswa aktif', 'selesai', 'Lomba');

        $this->get(route('pengajuan.berita.create'))
            ->assertOk()
            ->assertSee('Kegiatan milik mahasiswa aktif')
            ->assertDontSee('Pengajuan Lomba KMPIN VII 2026');

        $response = $this->post(route('pengajuan.store'), [
            'category' => 'Pengabdian',
            'title' => 'Pengabdian Desa Digital',
            'organizer' => 'Program Studi',
            'location' => 'Semarang',
            'start_date' => '2026-10-30',
            'end_date' => '2026-10-31',
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

    public function test_berita_submission_requires_complete_fields_and_cover_photo(): void
    {
        $this->from(route('pengajuan.berita.create'))
            ->post(route('pengajuan.berita.store'), [
                'intent' => 'submit',
                'source_type' => 'new',
                'activity_name' => 'Seminar Nasional',
                'category' => 'Info Akademik',
                'title' => 'Seminar Nasional TRK',
                'content' => 'Informasi kegiatan seminar.',
            ])
            ->assertSessionHasErrors('cover');
    }
}
