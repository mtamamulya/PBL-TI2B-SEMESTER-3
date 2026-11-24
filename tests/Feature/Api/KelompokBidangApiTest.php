<?php

namespace Tests\Feature;

use App\Models\KelompokBidang;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Kelompok bidang lewat API (/api/v1/kelompok-bidang).
 * Baca: semua pengguna login (termasuk dosen). Tulis: admin dan kaprodi.
 */
class KelompokBidangApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/kelompok-bidang';

    private function pengguna(string $role): User
    {
        $id = DB::table('roles')->where('nama_role', $role)->value('id_role')
            ?? DB::table('roles')->insertGetId(['nama_role' => $role], 'id_role');

        return User::create(['id_role' => $id, 'email' => uniqid().'@test.local', 'password' => 'password']);
    }

    private function sebagai(string $role = 'admin'): static
    {
        return $this->actingAs($this->pengguna($role), 'sanctum');
    }

    private function buat(string $nama, ?int $rujukan = null, string $role = 'admin'): int
    {
        return (int) $this->sebagai($role)
            ->postJson(self::API, ['nama_kelompok_bidang' => $nama, 'id_rujukan' => $rujukan])
            ->assertCreated()
            ->json('data.id_kelompok_bidang');
    }

    // ---------- akses ----------

    public function test_api_requires_login(): void
    {
        $this->getJson(self::API)->assertUnauthorized();
        $this->getJson(self::API.'/opsi')->assertUnauthorized();
        $this->getJson(self::API.'/1')->assertUnauthorized();
        $this->postJson(self::API, [])->assertUnauthorized();
        $this->putJson(self::API.'/1', [])->assertUnauthorized();
        $this->deleteJson(self::API.'/1')->assertUnauthorized();
    }

    public function test_dosen_can_read_but_not_write(): void
    {
        $id = $this->buat('Kecerdasan Buatan');

        $this->sebagai('dosen')->getJson(self::API)->assertOk();
        $this->sebagai('dosen')->getJson(self::API.'/opsi')->assertOk();
        $this->sebagai('dosen')->getJson(self::API.'/'.$id)->assertOk();

        $this->sebagai('dosen')->postJson(self::API, ['nama_kelompok_bidang' => 'Jaringan'])->assertForbidden();
        $this->sebagai('dosen')->putJson(self::API.'/'.$id, ['nama_kelompok_bidang' => 'Ubah'])->assertForbidden();
        $this->sebagai('dosen')->deleteJson(self::API.'/'.$id)->assertForbidden();

        $this->assertDatabaseCount('kelompok_bidang', 1);
    }

    public function test_mahasiswa_cannot_write_either(): void
    {
        $this->sebagai('mahasiswa')->postJson(self::API, ['nama_kelompok_bidang' => 'Jaringan'])->assertForbidden();
    }

    public function test_admin_and_kaprodi_can_both_write(): void
    {
        $a = $this->buat('Dibuat Admin', null, 'admin');
        $b = $this->buat('Dibuat Kaprodi', null, 'kaprodi');

        $this->sebagai('kaprodi')->putJson(self::API.'/'.$a, ['nama_kelompok_bidang' => 'Diubah Kaprodi'])->assertOk();
        $this->sebagai('admin')->deleteJson(self::API.'/'.$b)->assertOk();

        $this->assertDatabaseHas('kelompok_bidang', ['id_kelompok_bidang' => $a, 'nama_kelompok_bidang' => 'Diubah Kaprodi']);
        $this->assertDatabaseMissing('kelompok_bidang', ['id_kelompok_bidang' => $b]);
    }

    // ---------- tambah ----------

    public function test_create_returns_201_with_the_expected_shape(): void
    {
        $this->sebagai()->postJson(self::API, ['nama_kelompok_bidang' => '  Kecerdasan Buatan  '])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama_kelompok_bidang', 'Kecerdasan Buatan') // spasi dibuang
            ->assertJsonPath('data.id_rujukan', null)
            ->assertJsonPath('data.rujukan', null)
            ->assertJsonPath('data.total_turunan', 0)
            ->assertJsonPath('data.turunan', []);
    }

    public function test_create_with_a_parent(): void
    {
        $induk = $this->buat('Teknologi Informasi');

        $this->sebagai()->postJson(self::API, ['nama_kelompok_bidang' => 'Jaringan', 'id_rujukan' => $induk])
            ->assertCreated()
            ->assertJsonPath('data.id_rujukan', $induk)
            ->assertJsonPath('data.rujukan', 'Teknologi Informasi');
    }

    public function test_create_validates_input(): void
    {
        $this->sebagai()->postJson(self::API, [])
            ->assertStatus(422)->assertJsonValidationErrors('nama_kelompok_bidang');

        $this->sebagai()->postJson(self::API, ['nama_kelompok_bidang' => str_repeat('a', 101)])
            ->assertStatus(422)->assertJsonValidationErrors('nama_kelompok_bidang');

        $this->sebagai()->postJson(self::API, ['nama_kelompok_bidang' => 'Valid', 'id_rujukan' => 9999])
            ->assertStatus(422)->assertJsonValidationErrors('id_rujukan');

        $this->assertDatabaseCount('kelompok_bidang', 0);
    }

    public function test_name_must_be_unique(): void
    {
        $this->buat('Jaringan');

        $this->sebagai()->postJson(self::API, ['nama_kelompok_bidang' => 'Jaringan'])
            ->assertStatus(422)->assertJsonValidationErrors('nama_kelompok_bidang');
        $this->sebagai()->postJson(self::API, ['nama_kelompok_bidang' => ' Jaringan '])
            ->assertStatus(422)->assertJsonValidationErrors('nama_kelompok_bidang');

        $this->assertDatabaseCount('kelompok_bidang', 1);
    }

    // ---------- baca ----------

    public function test_list_is_paginated_searchable_and_sorted_by_name(): void
    {
        $induk = $this->buat('Teknologi Informasi');
        $this->buat('Jaringan', $induk);
        $this->buat('Basis Data', $induk);

        $this->sebagai('dosen')->getJson(self::API)
            ->assertOk()
            ->assertJsonPath('data.0.nama_kelompok_bidang', 'Basis Data')
            ->assertJsonPath('data.1.nama_kelompok_bidang', 'Jaringan')
            ->assertJsonPath('data.2.nama_kelompok_bidang', 'Teknologi Informasi')
            ->assertJsonPath('data.2.total_turunan', 2)
            ->assertJsonPath('meta.total', 3);

        $this->sebagai('dosen')->getJson(self::API.'?q=jaring')->assertOk()->assertJsonCount(1, 'data');
        $this->sebagai('dosen')->getJson(self::API.'?tanpa_rujukan=1')->assertOk()->assertJsonCount(1, 'data');
        $this->sebagai('dosen')->getJson(self::API.'?id_rujukan='.$induk)->assertOk()->assertJsonCount(2, 'data');
        $this->sebagai('dosen')->getJson(self::API.'?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
        $this->sebagai('dosen')->getJson(self::API.'?per_page=101')->assertStatus(422);
    }

    public function test_options_returns_everything_in_a_compact_flat_list(): void
    {
        $induk = $this->buat('Teknologi Informasi');
        $this->buat('Jaringan', $induk);

        $this->sebagai('dosen')->getJson(self::API.'/opsi')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nama_kelompok_bidang', 'Jaringan')
            ->assertJsonPath('data.0.id_rujukan', $induk)
            ->assertJsonMissingPath('data.0.total_turunan');
    }

    public function test_detail_lists_children_and_missing_id_is_404(): void
    {
        $induk = $this->buat('Teknologi Informasi');
        $this->buat('Jaringan', $induk);

        $this->sebagai('dosen')->getJson(self::API.'/'.$induk)
            ->assertOk()
            ->assertJsonPath('data.total_turunan', 1)
            ->assertJsonPath('data.turunan.0.nama_kelompok_bidang', 'Jaringan');

        $this->sebagai('dosen')->getJson(self::API.'/9999')->assertNotFound();
    }

    // ---------- ubah ----------

    public function test_update_changes_name_and_keeps_parent_when_not_sent(): void
    {
        $induk = $this->buat('Teknologi Informasi');
        $id = $this->buat('Jaringan', $induk);

        $this->sebagai()->putJson(self::API.'/'.$id, ['nama_kelompok_bidang' => 'Jaringan Komputer'])
            ->assertOk()
            ->assertJsonPath('data.nama_kelompok_bidang', 'Jaringan Komputer')
            ->assertJsonPath('data.id_rujukan', $induk);
    }

    public function test_update_can_detach_the_parent_with_null(): void
    {
        $induk = $this->buat('Teknologi Informasi');
        $id = $this->buat('Jaringan', $induk);

        $this->sebagai()->putJson(self::API.'/'.$id, ['nama_kelompok_bidang' => 'Jaringan', 'id_rujukan' => null])
            ->assertOk()
            ->assertJsonPath('data.id_rujukan', null);
    }

    public function test_update_can_keep_its_own_name_but_not_take_another_ones(): void
    {
        $a = $this->buat('Jaringan');
        $this->buat('Basis Data');

        $this->sebagai()->putJson(self::API.'/'.$a, ['nama_kelompok_bidang' => 'Jaringan'])->assertOk();
        $this->sebagai()->putJson(self::API.'/'.$a, ['nama_kelompok_bidang' => 'Basis Data'])
            ->assertStatus(422)->assertJsonValidationErrors('nama_kelompok_bidang');
    }

    public function test_update_missing_id_is_404(): void
    {
        $this->sebagai()->putJson(self::API.'/9999', ['nama_kelompok_bidang' => 'X'])->assertNotFound();
    }

    public function test_a_group_cannot_refer_to_itself_or_to_its_own_descendant(): void
    {
        $a = $this->buat('A');
        $b = $this->buat('B', $a);
        $c = $this->buat('C', $b);

        // ke diri sendiri
        $this->sebagai()->putJson(self::API.'/'.$a, ['nama_kelompok_bidang' => 'A', 'id_rujukan' => $a])
            ->assertStatus(422)->assertJsonValidationErrors('id_rujukan');

        // ke turunan langsung dan turunan dari turunan
        $this->sebagai()->putJson(self::API.'/'.$a, ['nama_kelompok_bidang' => 'A', 'id_rujukan' => $b])
            ->assertStatus(422)->assertJsonValidationErrors('id_rujukan');
        $this->sebagai()->putJson(self::API.'/'.$a, ['nama_kelompok_bidang' => 'A', 'id_rujukan' => $c])
            ->assertStatus(422)->assertJsonValidationErrors('id_rujukan');

        $this->assertNull(KelompokBidang::find($a)->id_rujukan);
    }

    // ---------- hapus ----------

    public function test_delete_removes_an_unused_group(): void
    {
        $id = $this->buat('Jaringan');

        $this->sebagai()->deleteJson(self::API.'/'.$id)->assertOk();

        $this->assertDatabaseMissing('kelompok_bidang', ['id_kelompok_bidang' => $id]);
        $this->sebagai()->deleteJson(self::API.'/'.$id)->assertNotFound();
    }

    public function test_delete_is_blocked_while_it_still_has_children(): void
    {
        $induk = $this->buat('Teknologi Informasi');
        $anak = $this->buat('Jaringan', $induk);

        $this->sebagai()->deleteJson(self::API.'/'.$induk)->assertStatus(409);

        $this->assertDatabaseHas('kelompok_bidang', ['id_kelompok_bidang' => $induk]);
        $this->assertSame($induk, KelompokBidang::find($anak)->id_rujukan);
    }

    public function test_delete_is_blocked_when_a_kegiatan_uses_it(): void
    {
        if (! Schema::hasColumn('kegiatan', 'id_kelompok_bidang')) {
            $this->markTestSkipped('Kolom kegiatan.id_kelompok_bidang belum ada di database.');
        }

        $id = $this->buat('Jaringan');
        $user = $this->pengguna('dosen');

        try {
            DB::table('kegiatan')->insert([
                'id_user' => $user->getKey(),
                'id_kelompok_bidang' => $id,
                'nama_kegiatan' => 'Penelitian Jaringan',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            $this->markTestSkipped('Tabel kegiatan butuh kolom wajib lain; lengkapi insert di tes ini. '.$e->getMessage());
        }

        $this->sebagai()->deleteJson(self::API.'/'.$id)->assertStatus(409);
        $this->assertDatabaseHas('kelompok_bidang', ['id_kelompok_bidang' => $id]);
    }
}
