<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MahasiswaProfilSandiTest extends TestCase
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

        $idUser = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'student-profile@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mahasiswa')->insert([
            'id_user' => $idUser,
            'nim' => '202443325115',
            'nama' => 'Mahasiswa OTP',
            'tahun_masuk' => 2024,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($idUser);
    }

    public function test_profile_otp_request_is_forwarded_with_the_session_token(): void
    {
        config(['api_client.base_url' => 'https://api.test/api/v1']);
        Http::fake([
            'https://api.test/api/v1/profil/ganti-sandi/kirim-otp' => Http::response([
                'success' => true,
                'message' => 'Kode OTP telah dikirim ke email Anda.',
                'data' => null,
            ]),
        ]);

        $this->actingAs($this->student())
            ->withSession(['api_token' => 'mahasiswa-session-token'])
            ->postJson(route('mahasiswa.profil.sandi.kirim-otp'))
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://api.test/api/v1/profil/ganti-sandi/kirim-otp'
            && $request->hasHeader('Authorization', 'Bearer mahasiswa-session-token'));
    }

    public function test_profile_uses_the_authenticated_student_enrollment_for_program_data(): void
    {
        $student = $this->student();
        $idMahasiswa = $student->mahasiswa->getKey();
        $idProdi = DB::table('prodi')->insertGetId([
            'kode_prodi' => 'TEST-'.substr(uniqid(), -8),
            'nama_prodi' => 'Program Studi Mahasiswa Uji',
            'jenjang' => 'D4',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idTahun = DB::table('tahun_akademik')->insertGetId([
            'tahun_akademik' => 'Ganjil 2026/2027',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idTingkat = DB::table('tingkat')->insertGetId([
            'tingkat' => 1,
            'keterangan' => 'Tingkat 1',
        ]);
        $idKelas = DB::table('kelas')->insertGetId([
            'jenis_kelas' => 'A',
            'keterangan' => 'Kelas A',
        ]);
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

        $this->actingAs($student)
            ->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertSee('Program Studi Mahasiswa Uji')
            ->assertSee('Jenjang D4')
            ->assertSee('Angkatan 2024');
    }

    public function test_profile_derives_program_from_a_valid_student_nim_without_enrollment(): void
    {
        $student = $this->student();
        DB::table('mahasiswa')->where('id_user', $student->getKey())
            ->update(['nim' => '4.33.25.1.11', 'tahun_masuk' => 2025]);
        config([
            'admin.prodi.kode_nim' => '33',
            'admin.prodi.kode' => 'TRK',
            'admin.prodi.nama' => 'Teknologi Rekayasa Komputer',
            'admin.prodi.jenjang' => 'D4',
        ]);

        $this->actingAs($student)
            ->get(route('mahasiswa.profil'))
            ->assertOk()
            ->assertSee('Teknologi Rekayasa Komputer')
            ->assertSee('Jenjang D4')
            ->assertSee('Angkatan 2025');
    }

    public function test_simpan_updates_the_session_token_without_returning_it_to_the_browser(): void
    {
        config(['api_client.base_url' => 'https://api.test/api/v1']);
        Http::fake([
            'https://api.test/api/v1/profil/ganti-sandi/simpan' => Http::response([
                'success' => true,
                'message' => 'Kata sandi berhasil diubah.',
                'data' => ['token' => 'new-mahasiswa-session-token'],
            ]),
        ]);

        $this->actingAs($this->student())
            ->withSession(['api_token' => 'old-mahasiswa-session-token'])
            ->postJson(route('mahasiswa.profil.sandi.simpan'), [
                'otp_id' => 17,
                'password' => 'PasswordBaru123!',
                'password_confirmation' => 'PasswordBaru123!',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.token')
            ->assertSessionHas('api_token', 'new-mahasiswa-session-token');

        Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://api.test/api/v1/profil/ganti-sandi/simpan'
            && $request->hasHeader('Authorization', 'Bearer old-mahasiswa-session-token')
            && $request['otp_id'] === 17
            && $request['password'] === 'PasswordBaru123!'
            && $request['password_confirmation'] === 'PasswordBaru123!');
    }
}
