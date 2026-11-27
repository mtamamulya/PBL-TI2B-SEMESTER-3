<?php

namespace Tests\Feature\Api;

use App\Models\Dosen;
use App\Models\IsianKategori;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kategori kegiatan + isian form lewat API (/api/v1/kategori-kegiatan).
 * Baca: semua pengguna login. Tulis: khusus admin.
 */
class KategoriKegiatanApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/kategori-kegiatan';

    protected function setUp(): void
    {
        parent::setUp();

        $idJenisMahasiswa = DB::table('jenis_kegiatan')
            ->where('nama_jenis_kegiatan', 'Kegiatan Mahasiswa')
            ->value('id_jenis_kegiatan');

        if ($idJenisMahasiswa) {
            $idKategori = DB::table('kategori')
                ->where('id_jenis_kegiatan', $idJenisMahasiswa)
                ->pluck('id_kategori');

            DB::table('isian_kategori')->whereIn('id_kategori', $idKategori)->delete();
            DB::table('kategori')->whereIn('id_kategori', $idKategori)->delete();
            DB::table('jenis_kegiatan')->where('id_jenis_kegiatan', $idJenisMahasiswa)->delete();
        }
    }

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

    private function jenis(string $nama = 'Kegiatan Mahasiswa'): JenisKegiatan
    {
        return JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => $nama]);
    }

    private function payload(array $ganti = []): array
    {
        return array_replace([
            'id_jenis_kegiatan' => $this->jenis()->getKey(),
            'nama_kategori' => 'Lomba',
            'deskripsi' => 'Kompetisi',
            'kebutuhan' => 'surat',
            'isi_surat' => 'Surat persetujuan',
            'isian' => [
                ['input' => 'Nama tim', 'jenis_input' => 'teks', 'wajib' => true, 'jumlah_isian' => 1],
                ['input' => 'Tingkat kejuaraan', 'jenis_input' => 'tingkat_kejuaraan', 'wajib' => false, 'jumlah_isian' => 1],
            ],
        ], $ganti);
    }

    private function buat(array $ganti = []): int
    {
        return (int) $this->sebagai()->postJson(self::API, $this->payload($ganti))->assertCreated()->json('data.id_kategori');
    }

    // ---------- akses ----------

    public function test_api_requires_login(): void
    {
        $this->getJson(self::API)->assertUnauthorized();
        $this->getJson(self::API.'/1')->assertUnauthorized();
        $this->postJson(self::API, [])->assertUnauthorized();
        $this->getJson('/api/v1/jenis-kegiatan')->assertUnauthorized();
        $this->getJson('/api/v1/tipe-isian')->assertUnauthorized();
    }

    public function test_non_admin_can_read_but_not_write(): void
    {
        $id = $this->buat();

        $this->sebagai('mahasiswa')->getJson(self::API)->assertOk();
        $this->sebagai('mahasiswa')->getJson(self::API.'/'.$id)->assertOk();

        $this->sebagai('mahasiswa')->postJson(self::API, $this->payload(['nama_kategori' => 'Magang']))->assertForbidden();
        $this->sebagai('kaprodi')->putJson(self::API.'/'.$id, $this->payload())->assertForbidden();
        $this->sebagai('dosen')->deleteJson(self::API.'/'.$id)->assertForbidden();

        $this->assertDatabaseCount('kategori', 1);
    }

    // ---------- simpan ----------

    public function test_admin_can_store_a_category_with_its_form_fields(): void
    {
        $this->sebagai()->postJson(self::API, $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama_kategori', 'Lomba')
            ->assertJsonPath('data.jenis', 'Kegiatan Mahasiswa')
            ->assertJsonPath('data.kebutuhan', 'surat')
            ->assertJsonCount(2, 'data.isian')
            ->assertJsonPath('data.isian.0.input', 'Nama tim')
            ->assertJsonPath('data.isian.0.wajib', true)
            ->assertJsonPath('data.isian.1.jenis_input', 'tingkat_kejuaraan');

        $this->assertDatabaseHas('isian_kategori', ['input' => 'Nama tim', 'aturan' => 'NOT NULL', 'jumlah_isian' => 1]);
        $this->assertDatabaseHas('isian_kategori', ['input' => 'Tingkat kejuaraan', 'aturan' => 'NULL', 'jumlah_isian' => 1]);
    }

    public function test_jenis_can_be_sent_by_name_and_template_surat_is_accepted_as_isi_surat(): void
    {
        $this->jenis('Kegiatan Dosen');

        $this->sebagai()->postJson(self::API, [
            'jenis' => 'Kegiatan Dosen',
            'nama_kategori' => 'Seminar',
            'template_surat' => 'Isi surat',
        ])->assertCreated()
            ->assertJsonPath('data.jenis', 'Kegiatan Dosen')
            ->assertJsonPath('data.isi_surat', 'Isi surat')
            ->assertJsonPath('data.isian', []);
    }

    public function test_multiple_value_field_is_stored_as_jumlah_isian_zero(): void
    {
        $this->sebagai()->postJson(self::API, $this->payload(['isian' => [
            ['input' => 'Dokumen pendukung', 'jenis_input' => 'berkas', 'wajib' => true, 'jumlah_isian' => 0],
        ]]))->assertCreated()->assertJsonPath('data.isian.0.jumlah_isian', 0);

        $this->assertDatabaseHas('isian_kategori', ['input' => 'Dokumen pendukung', 'jumlah_isian' => 0]);
    }

    public function test_jumlah_isian_defaults_to_one_and_must_be_zero_or_one(): void
    {
        $this->sebagai()->postJson(self::API, $this->payload(['isian' => [
            ['input' => 'Dokumen pendukung', 'jenis_input' => 'berkas'],
        ]]))->assertCreated()->assertJsonPath('data.isian.0.jumlah_isian', 1);

        $this->sebagai()->postJson(self::API, $this->payload(['nama_kategori' => 'Magang', 'isian' => [
            ['input' => 'Nama tim', 'jenis_input' => 'teks', 'jumlah_isian' => 'mungkin'],
        ]]))->assertStatus(422)->assertJsonValidationErrors('isian.0.jumlah_isian');
    }

    public function test_store_validates_the_fields(): void
    {
        $this->sebagai()->postJson(self::API, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_jenis_kegiatan', 'nama_kategori']);

        $this->sebagai()->postJson(self::API, $this->payload(['kebutuhan' => 'lainnya']))
            ->assertStatus(422)->assertJsonValidationErrors('kebutuhan');

        $this->sebagai()->postJson(self::API, $this->payload(['isian' => [['input' => '', 'jenis_input' => 'ajaib']]]))
            ->assertStatus(422)->assertJsonValidationErrors(['isian.0.input', 'isian.0.jenis_input']);

        $this->assertDatabaseCount('kategori', 0);
    }

    public function test_field_names_must_be_different_within_a_category(): void
    {
        $this->sebagai()->postJson(self::API, $this->payload(['isian' => [
            ['input' => 'Nama tim', 'jenis_input' => 'teks'],
            ['input' => 'nama tim', 'jenis_input' => 'teks'],
        ]]))->assertStatus(422)->assertJsonValidationErrors('isian.1.input');
    }

    public function test_category_name_must_be_unique_within_the_same_jenis_only(): void
    {
        $this->buat();

        $this->sebagai()->postJson(self::API, $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('errors.nama_kategori.0', 'Nama kategori sudah ada pada jenis kegiatan ini.');

        $dosen = $this->jenis('Kegiatan Dosen');
        $this->sebagai()->postJson(self::API, $this->payload(['id_jenis_kegiatan' => $dosen->getKey()]))->assertCreated();
    }

    // ---------- baca ----------

    public function test_list_shows_field_count_and_filters(): void
    {
        $this->buat();
        $this->buat(['nama_kategori' => 'Magang', 'isian' => []]);
        $dosen = $this->jenis('Kegiatan Dosen');
        $this->buat(['id_jenis_kegiatan' => $dosen->getKey(), 'nama_kategori' => 'Seminar', 'kebutuhan' => 'kegiatan', 'isian' => []]);

        $this->sebagai('mahasiswa')->getJson(self::API)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);

        $this->sebagai('mahasiswa')->getJson(self::API.'?jenis=Kegiatan%20Mahasiswa')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->sebagai('mahasiswa')->getJson(self::API.'?kebutuhan=kegiatan')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nama_kategori', 'Seminar');

        $this->sebagai('mahasiswa')->getJson(self::API.'?q=lom')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.total_isian', 2);
    }

    public function test_show_returns_fields_in_creation_order_and_404_for_unknown_id(): void
    {
        $id = $this->buat();

        $this->sebagai('mahasiswa')->getJson(self::API.'/'.$id)
            ->assertOk()
            ->assertJsonCount(2, 'data.isian')
            ->assertJsonPath('data.isian.0.input', 'Nama tim')
            ->assertJsonPath('data.isian.0.pilihan', null)
            ->assertJsonPath('data.isian.1.input', 'Tingkat kejuaraan');

        $this->sebagai('mahasiswa')->getJson(self::API.'/9999')->assertNotFound();
    }

    public function test_standard_choice_fields_come_with_their_options(): void
    {
        $id = $this->buat();

        $this->sebagai('mahasiswa')->getJson(self::API.'/'.$id)
            ->assertJsonCount(4, 'data.isian.1.pilihan')
            ->assertJsonPath('data.isian.1.pilihan.0.nilai', 'kabupaten/kota')
            ->assertJsonPath('data.isian.1.pilihan.3.nilai', 'internasional');
    }

    public function test_lecturer_choice_field_lists_lecturers_from_the_database(): void
    {
        $user = $this->pengguna('dosen');
        $dosen = Dosen::create(['id_user' => $user->getKey(), 'nip' => '1980010120100101', 'nama' => 'Kuwat Santoso']);

        $id = $this->buat(['isian' => [['input' => 'Dosen pembimbing', 'jenis_input' => 'dosen', 'wajib' => true]]]);

        $this->sebagai('mahasiswa')->getJson(self::API.'/'.$id)
            ->assertOk()
            ->assertJsonCount(1, 'data.isian.0.pilihan')
            ->assertJsonPath('data.isian.0.pilihan.0.nilai', (string) $dosen->getKey())
            ->assertJsonPath('data.isian.0.pilihan.0.label', 'Kuwat Santoso');
    }

    public function test_jenis_kegiatan_and_field_type_options(): void
    {
        $this->jenis('Kegiatan Dosen');
        $this->jenis('Kegiatan Mahasiswa');

        $this->sebagai('mahasiswa')->getJson('/api/v1/jenis-kegiatan')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nama_jenis_kegiatan', 'Kegiatan Dosen');

        $tipe = collect($this->sebagai('mahasiswa')->getJson('/api/v1/tipe-isian')->assertOk()->json('data'));
        $this->assertTrue($tipe->contains('nilai', 'teks'));
        $this->assertTrue($tipe->contains('nilai', 'tingkat_kejuaraan'));
        $this->assertTrue($tipe->contains('nilai', 'jenis_prestasi'));
    }

    // ---------- ubah ----------

    public function test_update_syncs_fields_by_id_adding_updating_and_removing(): void
    {
        $id = $this->buat();
        $idTim = IsianKategori::where('input', 'Nama tim')->value('id_isian_kategori');

        $this->sebagai()->putJson(self::API.'/'.$id, $this->payload([
            'nama_kategori' => 'Lomba Nasional',
            'isian' => [
                ['id_isian_kategori' => $idTim, 'input' => 'Nama tim lomba', 'jenis_input' => 'teks', 'wajib' => false, 'jumlah_isian' => 1],
                ['input' => 'Anggota', 'jenis_input' => 'teks', 'wajib' => true, 'jumlah_isian' => 1],
            ],
        ]))->assertOk()
            ->assertJsonPath('data.nama_kategori', 'Lomba Nasional')
            ->assertJsonCount(2, 'data.isian')
            ->assertJsonPath('data.isian.0.id_isian_kategori', $idTim)
            ->assertJsonPath('data.isian.0.input', 'Nama tim lomba')
            ->assertJsonPath('data.isian.0.wajib', false)
            ->assertJsonPath('data.isian.1.input', 'Anggota')
            ->assertJsonPath('data.isian.1.jumlah_isian', 1);

        $this->assertDatabaseMissing('isian_kategori', ['input' => 'Tingkat kejuaraan']);
        $this->assertDatabaseCount('isian_kategori', 2);
    }

    public function test_update_cannot_touch_a_field_of_another_category(): void
    {
        $id = $this->buat();
        $lain = $this->buat(['nama_kategori' => 'Magang']);
        $idLain = IsianKategori::where('id_kategori', $lain)->value('id_isian_kategori');

        $this->sebagai()->putJson(self::API.'/'.$id, $this->payload(['isian' => [
            ['id_isian_kategori' => $idLain, 'input' => 'Dibajak', 'jenis_input' => 'teks'],
        ]]))->assertStatus(422)->assertJsonValidationErrors('isian.0.id_isian_kategori');

        $this->assertDatabaseMissing('isian_kategori', ['input' => 'Dibajak']);
        $this->assertDatabaseCount('isian_kategori', 4);
    }

    public function test_update_without_isian_keeps_fields_and_empty_isian_clears_them(): void
    {
        $id = $this->buat();
        $tanpa = $this->payload();
        unset($tanpa['isian']);

        $this->sebagai()->putJson(self::API.'/'.$id, $tanpa)->assertOk()->assertJsonCount(2, 'data.isian');
        $this->assertDatabaseCount('isian_kategori', 2);

        $this->sebagai()->putJson(self::API.'/'.$id, $this->payload(['isian' => []]))->assertOk()->assertJsonCount(0, 'data.isian');
        $this->assertDatabaseCount('isian_kategori', 0);
    }

    public function test_saving_update_without_name_change_is_not_rejected_as_duplicate(): void
    {
        $id = $this->buat();

        $this->sebagai()->putJson(self::API.'/'.$id, $this->payload())->assertOk();
    }

    public function test_update_unknown_id_is_404(): void
    {
        $this->sebagai()->putJson(self::API.'/9999', $this->payload())->assertNotFound();
    }

    // ---------- hapus ----------

    public function test_destroy_removes_the_category_and_its_fields(): void
    {
        $id = $this->buat();

        $this->sebagai()->deleteJson(self::API.'/'.$id)->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('kategori', 0);
        $this->assertDatabaseCount('isian_kategori', 0);
        $this->assertNull(Kategori::find($id));
    }

    public function test_destroy_unknown_id_is_404(): void
    {
        $this->sebagai()->deleteJson(self::API.'/9999')->assertNotFound();
    }
}
