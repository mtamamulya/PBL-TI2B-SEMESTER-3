<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MahasiswaCourseScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_mata_kuliah_memakai_angkatan_dan_prodi_akun_login(): void
    {
        DB::table('roles')->insert([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idUser = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => 'mahasiswa-kurikulum@test.local',
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idMahasiswa = DB::table('mahasiswa')->insertGetId([
            'id_user' => $idUser,
            'nim' => '2025-1234',
            'nama' => 'Mahasiswa Kurikulum',
            'tahun_masuk' => 2025,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $idProdi = DB::table('prodi')->insertGetId([
            'kode_prodi' => 'TRK-TEST',
            'nama_prodi' => 'Teknologi Rekayasa Komputer',
            'jenjang' => 'D4',
        ]);
        $idTahun = DB::table('tahun_akademik')->insertGetId([
            'tahun_akademik' => '2025/2026 Ganjil',
            'status' => true,
        ]);
        $idKelas = DB::table('kelas')->insertGetId([
            'jenis_kelas' => 'A',
            'keterangan' => 'Kelas A',
        ]);
        $idTingkat = DB::table('tingkat')->insertGetId([
            'tingkat' => 1,
            'keterangan' => 'Tingkat 1',
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

        Http::fake([
            '127.0.0.1:8001/*' => Http::response([
                'status' => 'success',
                'data' => [],
                'meta' => ['current_page' => 1, 'per_page' => 100, 'total' => 0, 'last_page' => 1],
            ]),
        ]);

        $response = $this->actingAs(User::findOrFail($idUser))
            ->get(route('mahasiswa.mata-kuliah.katalog'))
            ->assertOk();

        $this->assertSame('2025-1234', $response->viewData('profil')['nim']);
        $this->assertSame('Teknologi Rekayasa Komputer', $response->viewData('profil')['prodi']);
        Http::assertSent(fn (ClientRequest $request) => str_starts_with($request->url(), 'http://127.0.0.1:8001/api/v1/mata-kuliah')
            && (string) $request['angkatan'] === '2025'
            && $request['kode_prodi'] === 'TRK-TEST');
    }
}
