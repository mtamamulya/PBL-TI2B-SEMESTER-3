<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DosenRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function loginDosen(): void
    {
        DB::table('roles')->insert(['id_role' => 3, 'nama_role' => 'dosen']);
        $this->actingAs(User::factory()->create(['id_role' => 3, 'status' => true]));
    }

    public function test_halaman_dosen_memerlukan_login_dan_peran_yang_tepat(): void
    {
        foreach (['/dosen/dashboard', '/profil', '/prestasi', '/berita', '/notifikasi', '/penelitian', '/pengabdian', '/pengajaran', '/pengajuan/dosen', '/pengajuan/mahasiswa', '/pengajuan'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
        foreach (['admin', 'kaprodi', 'mahasiswa'] as $role) {
            $id = DB::table('roles')->insertGetId(['nama_role' => $role], 'id_role');
            $this->actingAs(User::factory()->create(['id_role' => $id, 'status' => true]));
            $this->get('/profil')->assertRedirect(route('dashboard'));
            $this->get('/dashboard')->assertRedirect(route($role.'.dashboard'));
            $this->post('/pengajuan/dosen')->assertForbidden();
        }
    }

    public function test_tautan_lama_dan_tombol_form_tetap_di_area_dosen(): void
    {
        DB::table('roles')->insert(['id_role' => 3, 'nama_role' => 'dosen']);
        $this->actingAs(User::factory()->create(['id_role' => 3, 'status' => true]));

        foreach ([
            '/dashboard' => '/dosen/dashboard',
            '/kegiatan' => '/dosen/kegiatan',
            '/kegiatan/create' => '/dosen/kegiatan/create',
            '/kegiatan/42' => '/dosen/kegiatan/42',
            '/kegiatan/42/edit' => '/dosen/kegiatan/42/edit',
            '/pengajuan' => '/pengajuan/dosen',
            '/pengajuan/baru' => '/pengajuan/dosen/baru',
            '/pengajuan/dashboard' => '/dosen/dashboard',
            '/pengajuan/42' => '/pengajuan/dosen/42',
            '/pengajuan/42/edit' => '/pengajuan/dosen/42',
            '/pengajuan/berita' => '/berita',
            '/pengajuan/berita/baru' => '/berita/create',
            '/pengajuan/berita/42' => '/berita/42',
            '/pengajuan/berita/42/edit' => '/berita/42/edit',
        ] as $lama => $tujuan) {
            $this->get($lama)->assertRedirect($tujuan);
        }

        $this->get('/dosen/kegiatan/create')->assertOk()
            ->assertSee('href="'.route('dosen.kegiatan.index').'"', false)
            ->assertDontSee('href="'.route('kegiatan.index').'"', false)
            ->assertDontSee('Tampilan mahasiswa');
        $this->from('/mahasiswa/dashboard')->get('/pengajuan/dosen/42')->assertOk()
            ->assertDontSee('href="'.route('mahasiswa.dashboard').'"', false);
    }

    public function test_simpan_kegiatan_dosen_tetap_tersimpan_setelah_refresh(): void
    {
        DB::table('roles')->insert(['id_role' => 3, 'nama_role' => 'dosen']);
        $owner = User::factory()->create(['id_role' => 3, 'status' => true]);
        $jenis = DB::table('jenis_kegiatan')->insertGetId(['nama_jenis_kegiatan' => 'Kegiatan Dosen'], 'id_jenis_kegiatan');
        $category = DB::table('kategori')->insertGetId(['nama_kategori' => 'Seminar', 'id_jenis_kegiatan' => $jenis], 'id_kategori');
        $field = DB::table('isian_kategori')->insertGetId(['id_kategori' => $category, 'input' => 'lokasi'], 'id_isian_kategori');
        $id = DB::table('kegiatan')->insertGetId(['id_user' => $owner->getKey(), 'id_kategori' => $category, 'nama_kegiatan' => 'Seminar lama'], 'id_kegiatan');
        $edit = route('dosen.kegiatan.edit', $id);
        $update = route('dosen.kegiatan.update', $id);
        $show = route('dosen.kegiatan.show', $id);

        $this->actingAs($owner)->get($edit)->assertOk()->assertSee('Seminar lama')->assertSee($update);
        $this->put($update, ['nama_kegiatan' => 'Seminar baru', 'detail' => [$field => 'Gedung A']])->assertRedirect($show);
        $this->get($show)->assertOk()->assertSee('Seminar baru');
        $this->get($show)->assertOk()->assertSee('Seminar baru')->assertDontSee('Seminar lama');
        $this->get($edit)->assertOk()->assertSee('Gedung A');
        $this->get(route('dosen.kegiatan.index'))->assertOk()->assertSee('Seminar baru');
        $this->assertDatabaseHas('detail_kegiatan', ['id_kegiatan' => $id, 'isian' => 'Gedung A']);
        $this->actingAs(User::factory()->create(['id_role' => 3, 'status' => true]))
            ->put($update, ['nama_kegiatan' => 'Timpa'])->assertForbidden();
        $this->assertDatabaseHas('kegiatan', ['id_kegiatan' => $id, 'nama_kegiatan' => 'Seminar baru']);
    }

    public function test_dosen_dashboard_has_its_own_route_and_view(): void
    {
        $this->loginDosen();
        $this->get(route('dosen.dashboard'))
            ->assertOk()
            ->assertViewIs('dosen.dashboard')
            ->assertSee('href="'.route('dosen.dashboard').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('dosen-shell', false)
            ->assertSee('Dosen')
            ->assertSee('Penelitian')
            ->assertSee('Pengabdian')
            ->assertDontSee('Arya Bima')
            ->assertDontSee('Mahasiswa</span>', false);
    }

    public function test_dosen_navigation_routes_render_their_dosen_views(): void
    {
        $this->loginDosen();
        $routes = [
            ['dosen.kegiatan.index'],
            ['dosen.kegiatan.create'],
            ['penelitian.index'],
            ['penelitian.create'],
            ['penelitian.show', ['id' => 1]],
            ['penelitian.edit', ['id' => 1]],
            ['pengabdian.index'],
            ['pengajaran.index'],
            ['pengajaran.create'],
            // ['pengajaran.show', ['id' => 1]],
            // ['pengajaran.edit', ['id' => 1]],
            ['prestasi.index'],
            ['prestasi.create'],
            ['prestasi.show', ['id' => 1]],
            ['prestasi.edit', ['id' => 1]],
            ['berita.index'],
            ['berita.create'],
            ['berita.show', ['id' => 1]],
            ['berita.edit', ['id' => 1]],
            ['pengajuan.dosen.create'],
            ['pengajuan.dosen'],
            ['pengajuan.mahasiswa'],
            ['pengajuan.mahasiswa.show', ['id' => 1]],
            ['notifikasi'],
            ['profil'],
        ];

        $this->get(route('pengabdian.create'))->assertNotFound(); // Belum ada kategori pengabdian.
        $this->get(route('pengabdian.show', ['id' => 1]))->assertNotFound();
        $this->get(route('pengabdian.edit', ['id' => 1]))->assertNotFound();

        foreach ($routes as $route) {
            $routeName = $route[0];
            $parameters = $route[1] ?? [];

            $this->get(route($routeName, $parameters))
                ->assertOk()
                ->assertSee('class="frontend-shell dosen-shell', false)
                ->assertDontSee('class="frontend-shell mahasiswa-shell', false);
        }
    }

    public function test_dosen_activity_list_uses_its_own_content_instead_of_student_summary(): void
    {
        $this->loginDosen();
        $this->get(route('dosen.kegiatan.index'))
            ->assertOk()
            ->assertViewIs('dosen.kegiatan.index')
            ->assertSee('class="frontend-shell dosen-shell', false)
            ->assertSee('Ajukan Kegiatan')
            ->assertSee('Status kegiatan')
            ->assertDontSee('class="frontend-shell mahasiswa-shell', false)
            ->assertDontSee('Kegiatan per semester')
            ->assertDontSee('masa perkuliahan')
            ->assertDontSee('LOMBA')
            ->assertDontSee('VOLUNTEER')
            ->assertDontSee('MAGANG');

        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentId = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'student@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_user');

        $this->actingAs(User::findOrFail($studentId))
            ->get(route('kegiatan.index'))
            ->assertOk()
            ->assertSee('class="frontend-shell mahasiswa-shell', false)
            ->assertSee('Kegiatan per semester');
    }

    public function test_dosen_berita_list_keeps_detail_and_edit_links_in_dosen_routes(): void
    {
        $this->loginDosen();
        $this->get(route('berita.index'))
            ->assertOk()
            ->assertSee(url('/berita').'/${b.id}', false)
            ->assertDontSee(url('/pengajuan/berita'), false);
    }

    public function test_dosen_pengajuan_form_uses_dosen_store_route(): void
    {
        $roles = [];
        foreach ([1 => 'admin', 2 => 'kaprodi', 3 => 'dosen'] as $role => $name) {
            DB::table('roles')->insert(['id_role' => $role, 'nama_role' => $name]);
            $roles[$name] = User::factory()->create(['id_role' => $role, 'status' => true]);
        }
        $jenis = DB::table('jenis_kegiatan')->insertGetId(['nama_jenis_kegiatan' => 'Kegiatan Dosen'], 'id_jenis_kegiatan');
        $kategori = DB::table('kategori')->insertGetId(['nama_kategori' => 'Seminar', 'id_jenis_kegiatan' => $jenis], 'id_kategori');
        Storage::fake('public');
        $this->actingAs($roles['dosen'])->get(route('pengajuan.dosen.create'))->assertOk()
            ->assertSee('Seminar')->assertSee('action="'.route('pengajuan.dosen.store').'"', false);
        $response = $this->post(route('pengajuan.dosen.store'), [
            'id_kategori' => $kategori, 'title' => 'Seminar Integrasi',
            'lampiran' => UploadedFile::fake()->create('proposal.pdf', 12, 'application/pdf'),
        ]);
        $id = DB::table('kegiatan')->where('nama_kegiatan', 'Seminar Integrasi')->value('id_kegiatan');
        $this->assertNotNull($id);
        $response->assertRedirect(route('pengajuan.dosen.show', $id));
        $this->actingAs($roles['admin'])->get('/admin/pengajuan')->assertOk()->assertSee('Seminar Integrasi');
        $this->actingAs($roles['admin'], 'sanctum')->postJson("/api/v1/admin/pengajuan/$id/keputusan", [
            'aksi' => 'setujui', 'nomor_sk' => 'SK/123',
        ])->assertOk()->assertJsonPath('data.status', 'direview');
        $this->actingAs($roles['kaprodi'], 'web')->get('/kaprodi/pengajuan')->assertOk()->assertSee('Seminar Integrasi');

    }

    public function test_dosen_views_load_the_scripts_for_their_interactive_lists(): void
    {
        $this->loginDosen();
        $this->get(route('berita.index'))
            ->assertOk()
            ->assertSee("Alpine.data('beritaDaftar'", false);
    }

    public function test_dosen_pengajuan_pages_render_their_list_data(): void
    {
        $this->loginDosen();
        $this->get(route('dosen.dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('pengajuan.dosen').'"', false);

        $this->get(route('pengajuan.dosen'))
            ->assertOk()
            ->assertViewIs('dosen.pengajuan.dosen')
            ->assertSee('<title>Pengajuan Dosen | DOSEN</title>', false)
            ->assertSee('role="tablist" aria-label="Status pengajuan"', false)
            ->assertSee('Cari kegiatan atau nama dosen...')
            ->assertSee('aria-label="Tahapan pengajuan"', false)
            ->assertSee('Tinjau')
            ->assertSee("Alpine.data('pengajuanDosen'", false);

        $this->get(route('pengajuan.mahasiswa'))
            ->assertOk()
            ->assertViewIs('pengajuan.mahasiswa')
            ->assertSee("Alpine.data('pengajuanMahasiswa'", false);
    }
}
