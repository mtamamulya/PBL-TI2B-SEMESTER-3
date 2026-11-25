<?php

namespace Tests\Feature\Api;

use App\Models\Skim;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkimApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $idRole): User
    {
        $nama = [1 => 'admin', 2 => 'kaprodi', 3 => 'dosen', 4 => 'mahasiswa'][$idRole];
        DB::table('roles')->insertOrIgnore(['id_role' => $idRole, 'nama_role' => $nama]);

        return User::firstOrCreate(
            ['email' => "{$nama}@trk.ac.id"],
            ['id_role' => $idRole, 'password' => 'password', 'status' => true]
        );
    }

    private function sebagai(int $idRole): static
    {
        return $this->actingAs($this->user($idRole), 'sanctum');
    }

    /** Buat satu kegiatan yang memakai skim; kolom wajib diisi otomatis menurut skema. */
    private function kegiatanDenganSkim(int $idSkim): void
    {
        $user = $this->user(1);

        $idJenis = DB::table('jenis_kegiatan')->value('id_jenis_kegiatan')
            ?? DB::table('jenis_kegiatan')->insertGetId([
                'nama_jenis_kegiatan' => 'Kegiatan Dosen',
            ], 'id_jenis_kegiatan');

        $idKategori = DB::table('kategori')->value('id_kategori')
            ?? DB::table('kategori')->insertGetId([
                'id_jenis_kegiatan' => $idJenis,
                'nama_kategori' => 'Penelitian',
            ], 'id_kategori');

        $baris = [];
        foreach (Schema::getColumns('kegiatan') as $k) {
            if ($k['auto_increment'] || $k['nullable'] || $k['default'] !== null) {
                continue;
            }
            $tipe = strtolower($k['type_name']);
            $baris[$k['name']] = match (true) {
                str_contains($tipe, 'int') || str_contains($tipe, 'bool') => 1,
                $tipe === 'date' => '2026-01-01',
                str_contains($tipe, 'time') => now()->toDateTimeString(),
                $tipe === 'enum' && preg_match("/'([^']+)'/", $k['type'], $m) => $m[1],
                default => 'x',
            };
        }
        $baris['id_kategori'] = $idKategori;
        $baris['id_user'] = $user->id_user;
        $baris['id_skim'] = $idSkim;

        DB::table('kegiatan')->insert($baris);
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/v1/skim')->assertStatus(401);
        $this->postJson('/api/v1/skim', ['nama_skim' => 'A'])->assertStatus(401);
    }

    public function test_dosen_boleh_baca_tapi_tidak_boleh_tulis(): void
    {
        $skim = Skim::create(['nama_skim' => 'Penelitian Dasar']);

        $this->sebagai(3)->getJson('/api/v1/skim')->assertOk();
        $this->sebagai(3)->getJson("/api/v1/skim/{$skim->id_skim}")->assertOk();
        $this->sebagai(3)->postJson('/api/v1/skim', ['nama_skim' => 'Baru'])->assertStatus(403);
        $this->sebagai(4)->putJson("/api/v1/skim/{$skim->id_skim}", ['nama_skim' => 'Baru'])->assertStatus(403);
        $this->sebagai(4)->deleteJson("/api/v1/skim/{$skim->id_skim}")->assertStatus(403);
    }

    public function test_daftar_urut_nama_dengan_pencarian_dan_total_kegiatan(): void
    {
        Skim::create(['nama_skim' => 'Pengabdian']);
        Skim::create(['nama_skim' => 'Penelitian Dasar']);
        $dipakai = Skim::create(['nama_skim' => 'Penelitian Terapan']);
        $this->kegiatanDenganSkim($dipakai->id_skim);

        $res = $this->sebagai(1)->getJson('/api/v1/skim')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);
        $this->assertSame(['Penelitian Dasar', 'Penelitian Terapan', 'Pengabdian'], array_column($res->json('data'), 'nama_skim'));
        $this->assertSame(1, $res->json('data.1.total_kegiatan'));
        $this->assertSame(0, $res->json('data.0.total_kegiatan'));

        $cari = $this->sebagai(2)->getJson('/api/v1/skim?q=terapan')->assertOk();
        $this->assertCount(1, $cari->json('data'));
    }

    public function test_detail_dan_404(): void
    {
        $skim = Skim::create(['nama_skim' => 'Penelitian Dasar']);

        $this->sebagai(2)->getJson("/api/v1/skim/{$skim->id_skim}")
            ->assertOk()->assertJsonPath('data.nama_skim', 'Penelitian Dasar');
        $this->sebagai(2)->getJson('/api/v1/skim/9999')
            ->assertStatus(404)->assertJsonPath('message', 'Skim tidak ditemukan.');
    }

    public function test_admin_dan_kaprodi_dapat_menambah(): void
    {
        $this->sebagai(1)->postJson('/api/v1/skim', ['nama_skim' => 'Hibah Internal'])
            ->assertStatus(201)->assertJsonPath('data.nama_skim', 'Hibah Internal')
            ->assertJsonPath('data.total_kegiatan', 0);
        $this->sebagai(2)->postJson('/api/v1/skim', ['nama_skim' => 'Hibah Eksternal'])->assertStatus(201);

        $this->assertDatabaseHas('skim', ['nama_skim' => 'Hibah Internal']);
    }

    public function test_validasi_tambah(): void
    {
        Skim::create(['nama_skim' => 'Kembar']);

        $this->sebagai(1)->postJson('/api/v1/skim', ['nama_skim' => ''])->assertStatus(422)->assertJsonValidationErrors('nama_skim');
        $this->sebagai(1)->postJson('/api/v1/skim', ['nama_skim' => str_repeat('a', 101)])->assertStatus(422);
        $this->sebagai(1)->postJson('/api/v1/skim', ['nama_skim' => 'Kembar'])->assertStatus(422)->assertJsonValidationErrors('nama_skim');
    }

    public function test_ubah_skim_dan_nama_sendiri_boleh_dipertahankan(): void
    {
        $a = Skim::create(['nama_skim' => 'A']);
        Skim::create(['nama_skim' => 'B']);

        $this->sebagai(2)->putJson("/api/v1/skim/{$a->id_skim}", ['nama_skim' => 'A'])->assertOk();
        $this->sebagai(2)->putJson("/api/v1/skim/{$a->id_skim}", ['nama_skim' => 'A2'])
            ->assertOk()->assertJsonPath('data.nama_skim', 'A2');
        $this->sebagai(2)->putJson("/api/v1/skim/{$a->id_skim}", ['nama_skim' => 'B'])->assertStatus(422);
        $this->sebagai(2)->putJson('/api/v1/skim/9999', ['nama_skim' => 'X'])->assertStatus(404);
    }

    public function test_hapus_skim_tidak_dipakai_berhasil(): void
    {
        $skim = Skim::create(['nama_skim' => 'Sementara']);

        $this->sebagai(1)->deleteJson("/api/v1/skim/{$skim->id_skim}")->assertOk();

        $this->assertDatabaseMissing('skim', ['id_skim' => $skim->id_skim]);
        $this->sebagai(1)->deleteJson("/api/v1/skim/{$skim->id_skim}")->assertStatus(404);
    }

    public function test_hapus_skim_yang_dipakai_kegiatan_ditolak_409(): void
    {
        $skim = Skim::create(['nama_skim' => 'Dipakai']);
        $this->kegiatanDenganSkim($skim->id_skim);

        $this->sebagai(1)->deleteJson("/api/v1/skim/{$skim->id_skim}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Skim masih dipakai kegiatan dan tidak dapat dihapus.');

        $this->assertDatabaseHas('skim', ['id_skim' => $skim->id_skim]);
    }
}
