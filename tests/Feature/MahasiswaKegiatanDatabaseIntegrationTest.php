<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KegiatanController;
use App\Http\Controllers\PengajuanController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MahasiswaKegiatanDatabaseIntegrationTest extends TestCase
{
    private string $originalConnection;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-18 10:00:00');
        $this->originalConnection = DB::getDefaultConnection();
        config(['database.connections.activity_integration' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('activity_integration');
        DB::setDefaultConnection('activity_integration');
        config(['session.driver' => 'array']);

        $this->createSchema();
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
        $this->student = User::query()->findOrFail($studentId);
        DB::table('mahasiswa')->insert([
            'id_user' => $studentId,
            'nim' => '20240001',
            'nama' => 'Mahasiswa Uji',
            'tahun_masuk' => 2024,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('activity_integration');
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_direct_activity_addition_updates_database_cards_dashboard_and_semester_chart_without_submission(): void
    {
        $request = Request::create('/mahasiswa/kegiatan', 'GET');
        $request->setUserResolver(fn () => $this->student);
        $initial = app(KegiatanController::class)->index($request)->getData();
        $this->assertCount(0, $initial['allActivities']);
        $this->assertSame(0, $initial['categoryCounts']['LOMBA']);
        $this->assertCount(8, $initial['semesters']);

        $this->addVolunteerActivity('Kegiatan semester 1', '2024-10-10', true);
        $this->addVolunteerActivity('Kegiatan semester 2', '2025-03-10');

        $this->assertDatabaseCount('kegiatan', 2);
        $this->assertDatabaseCount('timeline', 0);
        $documentTypeId = DB::table('jenis_dokumen')
            ->where('nama_jenis_dokumen', 'Foto Kegiatan')
            ->value('id_jenis_dokumen');
        $this->assertNotNull($documentTypeId);
        $this->assertDatabaseHas('dokumen', [
            'id_kegiatan' => 1,
            'id_jenis_dokumen' => $documentTypeId,
            'nama_file' => 'Dokumentasi kegiatan',
            'tipe_dokumen' => 'Dokumentasi',
            'link_dokumen' => 'https://example.test/dokumentasi.jpg',
        ]);

        $activityPage = app(KegiatanController::class)->index($request)->getData();
        $this->assertCount(2, $activityPage['allActivities']);
        $this->assertSame(['Tercatat', 'Tercatat'], $activityPage['allActivities']->pluck('status')->all());
        $this->assertSame(2, $activityPage['categoryCounts']['VOLUNTEER']);
        $this->assertSame(1, $activityPage['semesters']->get(0)['value']);
        $this->assertSame(1, $activityPage['semesters']->get(1)['value']);

        $dashboard = Request::create('/mahasiswa/dashboard', 'GET');
        $dashboard->setUserResolver(fn () => $this->student);
        $dashboardData = app(DashboardController::class)->index($dashboard)->getData();
        $this->assertSame(0, $dashboardData['totalPengajuan']);
        $this->assertSame(2, $dashboardData['totalKegiatan']);
        $this->assertCount(0, $dashboardData['pengajuanTerbaru']);
    }

    public function test_submitted_student_activity_is_counted_as_pengajuan(): void
    {
        $request = Request::create(route('pengajuan.store'), 'POST', [
            'category' => 'Volunteer',
            'title' => 'Pengajuan Relawan',
            'organizer' => 'Komunitas',
            'role' => 'Mentor',
            'location' => 'Semarang',
            'start_date' => '2025-03-10',
            'end_date' => '2025-03-12',
        ]);
        $request->setUserResolver(fn () => $this->student);

        app(PengajuanController::class)->store($request);

        $this->assertDatabaseCount('kegiatan', 1);
        $this->assertDatabaseHas('timeline', ['status' => 'diajukan']);

        $activityRequest = Request::create('/mahasiswa/kegiatan', 'GET');
        $activityRequest->setUserResolver(fn () => $this->student);
        $activityPage = app(KegiatanController::class)->index($activityRequest)->getData();
        $this->assertSame('Diajukan', $activityPage['allActivities']->first()['status']);

        DB::table('timeline')->insert([
            'id_kegiatan' => 1,
            'status' => 'direview',
            'tanggal_status' => now()->addSecond(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $reviewedActivityPage = app(KegiatanController::class)->index($activityRequest)->getData();
        $this->assertSame('Direview', $reviewedActivityPage['allActivities']->first()['status']);

        $dashboard = Request::create('/mahasiswa/dashboard', 'GET');
        $dashboard->setUserResolver(fn () => $this->student);
        $dashboardData = app(DashboardController::class)->index($dashboard)->getData();
        $this->assertSame(1, $dashboardData['totalPengajuan']);
        $this->assertSame(1, $dashboardData['totalKegiatan']);
        $this->assertCount(1, $dashboardData['pengajuanTerbaru']);
    }

    public function test_activity_form_categories_match_dashboard_cards_and_accept_kkl_and_research(): void
    {
        $this->actingAs($this->student)
            ->get(route('kegiatan.create'))
            ->assertOk()
            ->assertSee('role="listbox"', false)
            ->assertSee('role="option"', false)
            ->assertSee('categoryPresentation[category].icon', false)
            ->assertSee('categoryPresentation[category].color', false)
            ->assertSee('Lomba')
            ->assertSee('Volunteer')
            ->assertSee('KKL')
            ->assertSee('Magang')
            ->assertSee('Pengabdian')
            ->assertSee('Penelitian');

        foreach (['KKL', 'Penelitian'] as $category) {
            $request = Request::create(route('kegiatan.store'), 'POST', [
                'category' => $category,
                'title' => 'Kegiatan '.$category,
                'organizer' => 'Program Studi',
                'location' => 'Semarang',
                'start_date' => '2026-10-30',
                'end_date' => '2026-10-31',
            ]);
            $request->setUserResolver(fn () => $this->student);

            app(PengajuanController::class)->storeActivity($request);
        }

        $this->assertDatabaseCount('kegiatan', 2);
        $this->assertDatabaseHas('kategori', ['nama_kategori' => 'KKL']);
        $this->assertDatabaseHas('kategori', ['nama_kategori' => 'Penelitian']);

        $activityRequest = Request::create('/mahasiswa/kegiatan', 'GET');
        $activityRequest->setUserResolver(fn () => $this->student);
        $activityPage = app(KegiatanController::class)->index($activityRequest)->getData();
        $this->assertSame(1, $activityPage['categoryCounts']['KKL']);
        $this->assertSame(1, $activityPage['categoryCounts']['PENELITIAN']);
    }

    private function addVolunteerActivity(string $title, string $startDate, bool $withDocument = false): void
    {
        $data = [
            'category' => 'Volunteer',
            'title' => $title,
            'organizer' => 'Komunitas Semarang',
            'role' => 'Mentor',
            'location' => 'Semarang',
            'start_date' => $startDate,
            'end_date' => Carbon::parse($startDate)->addDay()->toDateString(),
        ];
        if ($withDocument) {
            $data['documents'] = [[
                'name' => 'Dokumentasi kegiatan',
                'type' => 'Dokumentasi',
                'url' => 'https://example.test/dokumentasi.jpg',
            ]];
        }
        $request = Request::create(route('kegiatan.store'), 'POST', $data);
        $request->setUserResolver(fn () => $this->student);

        app(PengajuanController::class)->storeActivity($request);
    }

    private function createSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('id_role')->primary();
            $table->string('nama_role');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id('id_user');
            $table->unsignedBigInteger('id_role');
            $table->string('email');
            $table->string('password');
            $table->boolean('status');
            $table->timestamps();
        });
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->id('id_mahasiswa');
            $table->unsignedBigInteger('id_user');
            $table->string('nim');
            $table->string('nama');
            $table->unsignedInteger('tahun_masuk');
            $table->timestamps();
        });
        Schema::create('dosen', function (Blueprint $table) {
            $table->id('id_dosen');
            $table->unsignedBigInteger('id_user');
            $table->string('nip');
            $table->string('nama');
            $table->timestamps();
        });
        Schema::create('jenis_kegiatan', function (Blueprint $table) {
            $table->id('id_jenis_kegiatan');
            $table->string('nama_jenis_kegiatan')->unique();
            $table->timestamps();
        });
        Schema::create('kategori', function (Blueprint $table) {
            $table->id('id_kategori');
            $table->unsignedBigInteger('id_jenis_kegiatan');
            $table->string('nama_kategori');
            $table->text('deskripsi')->nullable();
            $table->text('isi_surat')->nullable();
            $table->string('kebutuhan')->default('kegiatan');
            $table->timestamps();
        });
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id('id_kegiatan');
            $table->unsignedBigInteger('id_kategori');
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_skim')->nullable();
            $table->unsignedBigInteger('id_kelompok_bidang')->nullable();
            $table->string('nama_kegiatan');
            $table->string('nomor_sk')->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->timestamps();
        });
        Schema::create('isian_kategori', function (Blueprint $table) {
            $table->id('id_isian_kategori');
            $table->unsignedBigInteger('id_kategori');
            $table->string('input');
            $table->string('jenis_input')->default('teks');
            $table->string('aturan')->default('NULL');
            $table->unsignedTinyInteger('jumlah_isian')->nullable()->default(1);
            $table->timestamps();
        });
        Schema::create('detail_kegiatan', function (Blueprint $table) {
            $table->id('id_detail_kegiatan');
            $table->unsignedBigInteger('id_kegiatan');
            $table->unsignedBigInteger('id_isian_kategori');
            $table->text('isian')->nullable();
            $table->timestamps();
        });
        Schema::create('timeline', function (Blueprint $table) {
            $table->id('id_timeline');
            $table->unsignedBigInteger('id_kegiatan');
            $table->string('status');
            $table->dateTime('tanggal_status');
            $table->text('catatan_revisi')->nullable();
            $table->timestamps();
        });
        Schema::create('jenis_dokumen', function (Blueprint $table) {
            $table->id('id_jenis_dokumen');
            $table->string('nama_jenis_dokumen', 100)->unique();
            $table->string('keterangan', 255)->nullable();
            $table->timestamps();
        });
        Schema::create('prestasi', function (Blueprint $table) {
            $table->id('id_prestasi');
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_kegiatan')->nullable();
            $table->string('jenis_prestasi')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->timestamps();
        });
        Schema::create('dokumen', function (Blueprint $table) {
            $table->id('id_dokumen');
            $table->unsignedBigInteger('id_kegiatan')->nullable();
            $table->unsignedBigInteger('id_detail_kegiatan')->nullable();
            $table->unsignedBigInteger('id_jenis_dokumen');
            $table->string('nama_file')->nullable();
            $table->string('tipe_dokumen')->nullable();
            $table->string('link_dokumen')->nullable();
            $table->timestamps();
            $table->foreign('id_jenis_dokumen')
                ->references('id_jenis_dokumen')
                ->on('jenis_dokumen');
        });
        Schema::create('anggota_kegiatan', function (Blueprint $table) {
            $table->id('id_anggota_kegiatan');
            $table->unsignedBigInteger('id_kegiatan')->nullable();
            $table->unsignedBigInteger('id_prestasi')->nullable();
            $table->unsignedBigInteger('id_user');
            $table->string('peran');
            $table->timestamps();
        });
    }
}
