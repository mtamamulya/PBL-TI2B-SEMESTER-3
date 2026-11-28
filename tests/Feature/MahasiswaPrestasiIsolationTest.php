<?php

namespace Tests\Feature;

use App\Models\Prestasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MahasiswaPrestasiIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function mahasiswa(string $email): User
    {
        DB::table('roles')->insertOrIgnore([
            'id_role' => 4,
            'nama_role' => 'mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $id = DB::table('users')->insertGetId([
            'id_role' => 4,
            'email' => $email,
            'password' => bcrypt('password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::findOrFail($id);
    }

    private function prestasi(User $owner, string $nama): Prestasi
    {
        return Prestasi::create([
            'id_user' => $owner->getKey(),
            'created_by' => $owner->getKey(),
            'capaian' => 'Juara 1',
            'jenis_prestasi' => 'akademik',
            'nama_lomba' => $nama,
            'penyelenggara' => 'Penyelenggara',
            'lokasi' => 'Semarang',
            'tingkat_kejuaraan' => 'nasional',
            'tanggal_mulai' => '2025-01-10',
            'tanggal_selesai' => '2025-01-11',
            'bentuk_partisipasi' => 'individu',
        ]);
    }

    public function test_mahasiswa_hanya_melihat_prestasi_milik_atau_yang_melibatkannya(): void
    {
        $mahasiswaA = $this->mahasiswa('mahasiswa-a@test.local');
        $mahasiswaB = $this->mahasiswa('mahasiswa-b@test.local');
        $prestasiA = $this->prestasi($mahasiswaA, 'Prestasi Milik A');
        $this->prestasi($mahasiswaB, 'Prestasi Milik B');

        $response = $this->actingAs($mahasiswaA)->get(route('mahasiswa.prestasi'));

        $response->assertOk()->assertViewIs('mahasiswa.mahasiswa.prestasi');
        $this->assertSame(
            [$prestasiA->nama_lomba],
            $response->viewData('prestasi')->pluck('nama')->all()
        );
    }

    public function test_mahasiswa_tidak_dapat_mengedit_atau_menghapus_prestasi_mahasiswa_lain(): void
    {
        $mahasiswaA = $this->mahasiswa('mahasiswa-a@test.local');
        $mahasiswaB = $this->mahasiswa('mahasiswa-b@test.local');
        $prestasiB = $this->prestasi($mahasiswaB, 'Prestasi Milik B');

        $this->actingAs($mahasiswaA)
            ->get(route('mahasiswa.prestasi.edit', $prestasiB->getKey()))
            ->assertNotFound();

        $this->delete(route('mahasiswa.prestasi.hapus', $prestasiB->getKey()))
            ->assertNotFound();

        $this->assertDatabaseHas('prestasi', ['id_prestasi' => $prestasiB->getKey()]);
    }
}
