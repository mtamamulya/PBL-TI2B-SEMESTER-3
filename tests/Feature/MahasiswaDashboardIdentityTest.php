<?php

namespace Tests\Feature;

use App\BackendKel2\Akademik\PeriodeAkademik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MahasiswaDashboardIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function student(): User
    {
        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'student-'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function createMahasiswaProfile(User $user, array $attributes = []): void
    {
        DB::table('mahasiswa')->insert(array_merge([
            'id_user' => $user->getKey(),
            'nim' => '2024'.substr(uniqid(), -8),
            'nama' => 'Mahasiswa Profil DB',
            'tahun_masuk' => 2024,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    public function test_dashboard_uses_authenticated_student_identity(): void
    {
        $student = $this->student();
        $this->createMahasiswaProfile($student, [
            'nim' => '4.33.25.1.11',
            'nama' => 'Dashboard Identity DB',
            'tahun_masuk' => 2025,
        ]);

        $this->actingAs($student)->get(route('mahasiswa.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Identity DB')
            ->assertSee('NIM:')
            ->assertSee('4.33.25.1.11')
            ->assertSee('Angkatan 2025')
            ->assertDontSee('NIP:');
    }

    public function test_student_dashboard_uses_database_student_and_academic_period(): void
    {
        $student = $this->student();
        $idMahasiswa = DB::table('mahasiswa')->insertGetId([
            'id_user' => $student->getKey(),
            'nim' => '2025-'.uniqid(),
            'nama' => 'Mahasiswa Dashboard DB',
            'tahun_masuk' => 2025,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tahun_akademik')->insert([
            'tahun_akademik' => 'Ganjil 2026/2027',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('mahasiswa.index'));
        $response->assertOk()
            ->assertViewIs('mahasiswa.mahasiswa.dashboard-mhs')
            ->assertDontSee('Kecerdasan Buatan');

        $this->assertSame('Mahasiswa Dashboard DB', $response->viewData('nama'));
        $this->assertSame(0, $response->viewData('ringkasan')['sks_tempuh']);
        $this->assertSame(0, $response->viewData('ringkasan')['jumlah_mk']);
        $this->assertSame(
            PeriodeAkademik::sekarang()->semesterKe(2025),
            $response->viewData('ringkasan')['semester_ke']
        );
        $this->assertDatabaseHas('mahasiswa', ['id_mahasiswa' => $idMahasiswa, 'id_user' => $student->getKey()]);
    }

    public function test_student_academic_dashboard_aggregates_enrolled_course_data(): void
    {
        $student = $this->student();
        $idMahasiswa = DB::table('mahasiswa')->insertGetId([
            'id_user' => $student->getKey(),
            'nim' => '2025-'.uniqid(),
            'nama' => 'Mahasiswa Berkegiatan',
            'tahun_masuk' => 2025,
        ]);
        $idProdi = DB::table('prodi')->insertGetId([
            'kode_prodi' => 'T'.substr(uniqid(), 0, 8),
            'nama_prodi' => 'Teknologi Rekayasa Komputer',
            'jenjang' => 'D4',
        ]);
        $idTahun = DB::table('tahun_akademik')->insertGetId([
            'tahun_akademik' => 'Ganjil 2026/2027',
            'status' => true,
        ]);
        $idKurikulum = DB::table('kurikulum')->insertGetId([
            'id_prodi' => $idProdi,
            'nama_kurikulum' => 'Kurikulum Uji',
            'tahun_terbit' => 2024,
            'status' => true,
        ]);
        $idMataKuliah = DB::table('mata_kuliah')->insertGetId([
            'id_kurikulum' => $idKurikulum,
            'semester' => 3,
            'kode_mk' => 'MK00000001',
            'nama_mk' => 'Basis Data dari TiDB',
            'sks_teori' => 3,
            'sks_praktik' => 1,
            'jam_teori' => 3,
            'jam_praktek' => 2,
        ]);
        DB::table('mata_kuliah')->insert([
            'id_kurikulum' => $idKurikulum,
            'semester' => 4,
            'kode_mk' => 'MK00000002',
            'nama_mk' => 'Mata Kuliah Belum Diambil',
            'sks_teori' => 3,
            'sks_praktik' => 0,
            'jam_teori' => 3,
            'jam_praktek' => 0,
        ]);
        $idTingkat = DB::table('tingkat')->insertGetId(['tingkat' => 2, 'keterangan' => 'Tingkat 2']);
        $idKelas = DB::table('kelas')->insertGetId(['jenis_kelas' => 'B', 'keterangan' => 'Kelas B']);
        $idEnrollmentTingkat = DB::table('enrollment_tingkat_kelas')->insertGetId([
            'id_kelas' => $idKelas,
            'id_prodi' => $idProdi,
            'id_tingkat' => $idTingkat,
            'id_tahun_akademik' => $idTahun,
        ]);
        DB::table('enrollment_mahasiswa_tingkat_kelas')->insert([
            'id_enrollment_tingkat_kelas' => $idEnrollmentTingkat,
            'id_mahasiswa' => $idMahasiswa,
        ]);
        $idEnrollmentMk = DB::table('enrollment_mata_kuliah')->insertGetId([
            'id_mk' => $idMataKuliah,
            'id_tahun_akademik' => $idTahun,
        ]);
        DB::table('roles')->insert([
            'id_role' => 3,
            'nama_role' => 'dosen',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idDosenUser = DB::table('users')->insertGetId([
            'id_role' => 3,
            'email' => 'dosen-'.uniqid().'@test.local',
            'password' => bcrypt('password'),
            'status' => true,
        ]);
        $idDosen = DB::table('dosen')->insertGetId([
            'id_user' => $idDosenUser,
            'nip' => 'D'.substr(uniqid(), 0, 19),
            'nama' => 'Dosen Database',
        ]);
        DB::table('enrollment_kelas_mata_kuliah')->insert([
            'id_enrollment_tingkat_kelas' => $idEnrollmentTingkat,
            'id_enrollment_mk' => $idEnrollmentMk,
            'id_dosen' => $idDosen,
        ]);

        $response = $this->actingAs($student)->get(route('mahasiswa.index'));
        $response->assertOk()->assertSee('Basis Data dari TiDB')->assertDontSee('Mata Kuliah Belum Diambil');

        $ringkasan = $response->viewData('ringkasan');
        $this->assertSame(4, $ringkasan['sks_tempuh']);
        $this->assertSame(7, $ringkasan['sks_total']);
        $this->assertSame(1, $ringkasan['jumlah_mk']);
        $this->assertSame(4, $ringkasan['sks_semester']);
        $this->assertSame('2026/2027', $ringkasan['tahun_akademik']);
        $this->assertSame('Ganjil', $ringkasan['jenis_semester']);
    }

    public function test_profile_and_header_show_the_logout_action(): void
    {
        $user = $this->student();
        $this->createMahasiswaProfile($user, [
            'nim' => '202443325115',
            'nama' => 'Mahasiswa Profil Database',
        ]);

        $this->actingAs($user)->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertViewIs('mahasiswa.mahasiswa.profil')
            ->assertSee('Mahasiswa Profil Database')
            ->assertSee('202443325115')
            ->assertSee('Angkatan 2024')
            ->assertSee('Mahasiswa • NIM 202443325115')
            ->assertDontSee('Arya Bima')
            ->assertSee('Keluar')
            ->assertSee('action="'.route('logout').'"', false);

        $this->get(route('mahasiswa.dashboard'))
            ->assertOk()
            ->assertSee('Mahasiswa Profil Database')
            ->assertSee('Mahasiswa • NIM 202443325115')
            ->assertDontSee('Arya Bima')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Keluar');
    }

    public function test_profile_password_modal_uses_the_profile_otp_api(): void
    {
        $user = $this->student();
        $user->update(['email' => 'ivan.mahasiswa@example.test']);
        $this->createMahasiswaProfile($user);

        $this->actingAs($user)
            ->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertSee('Kode OTP akan dikirim ke i*************@example.test.')
            ->assertSee('x-text="pesanError"', false)
            ->assertSee('Ubah email')
            ->assertSee('/api/v1/profil/ganti-email/kirim-otp', false)
            ->assertSee('/api/v1/profil/ganti-email/verifikasi', false)
            ->assertSee(str_replace('/', '\\/', route('mahasiswa.profil.sandi.kirim-otp')), false)
            ->assertSee(str_replace('/', '\\/', route('mahasiswa.profil.sandi.verifikasi')), false)
            ->assertSee(str_replace('/', '\\/', route('mahasiswa.profil.sandi.simpan')), false)
            ->assertDontSee('alert(\'Kata sandi berhasil disimpan!\')', false);
    }

    public function test_profile_shows_the_effective_local_otp_recipient(): void
    {
        $user = $this->student();
        $user->update(['email' => 'student-original@test.local']);
        DB::table('mahasiswa')->insert([
            'id_user' => $user->getKey(),
            'nim' => '43325111',
            'nama' => 'Mahasiswa Tes',
            'tahun_masuk' => 2024,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->app->detectEnvironment(fn () => 'local');
        config([
            'mahasiswa.otp_email' => 'ivan.43325111@mhs.polines.ac.id',
            'mahasiswa.otp_test_nim' => '43325111',
        ]);

        $maskedEmail = 'i'.str_repeat('*', strlen('ivan.43325111') - 1).'@mhs.polines.ac.id';
        $this->actingAs($user)
            ->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertSee('Kode OTP akan dikirim ke '.$maskedEmail.'.');
    }

    public function test_student_can_log_out_using_the_shared_logout_route(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_role');
        $userId = DB::table('users')->insertGetId([
            'id_role' => $roleId,
            'email' => 'mahasiswa-logout@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_user');

        $this->actingAs(User::findOrFail($userId))
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_student_can_log_in_and_is_redirected_to_the_dashboard(): void
    {
        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'id_role' => 4,
            'email' => 'mahasiswa-login@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'mahasiswa-login@test.local',
            'password' => 'password',
        ])
            ->assertRedirect(route('mahasiswa.dashboard'));

        $this->assertAuthenticated();
    }

    public function test_inactive_student_cannot_log_in(): void
    {
        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('users')->insert([
            'id_role' => 4,
            'email' => 'mahasiswa-inactive@test.local',
            'password' => bcrypt('password'),
            'status' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->post(route('login.attempt'), [
            'email' => 'mahasiswa-inactive@test.local',
            'password' => 'password',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
