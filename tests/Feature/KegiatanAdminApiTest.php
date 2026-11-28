<?php

namespace Tests\Feature;

use App\Models\AnggotaKegiatan;
use App\Models\JenisKegiatan;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/** API Data Kegiatan admin & kaprodi: daftar (hanya diterima), detail (anggota, dokumen, riwayat), tambah, hapus. */
class KegiatanAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private int $kategori;

    private int $jenisDokumen;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);

        $now = now();
        DB::table('jenis_kegiatan')->updateOrInsert(['nama_jenis_kegiatan' => 'Kegiatan Prodi'], ['created_at' => $now, 'updated_at' => $now]);
        $jenis = DB::table('jenis_kegiatan')->where('nama_jenis_kegiatan', 'Kegiatan Prodi')->value('id_jenis_kegiatan');
        $this->kategori = DB::table('kategori')->insertGetId([
            'id_jenis_kegiatan' => $jenis, 'nama_kategori' => 'Kategori Uji', 'kebutuhan' => 'kegiatan',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        foreach (['penyelenggara' => 'teks', 'tanggal_mulai' => 'tanggal'] as $input => $tipe) {
            DB::table('isian_kategori')->insert([
                'id_kategori' => $this->kategori, 'input' => $input, 'jenis_input' => $tipe,
                'aturan' => 'NULL', 'jumlah_isian' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $this->jenisDokumen = DB::table('jenis_dokumen')->insertGetId([
            'nama_jenis_dokumen' => 'Surat tugas uji', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function user(int $role): User
    {
        return User::query()->where('id_role', $role)->firstOrFail();
    }

    private function masuk(int $role): User
    {
        $u = $this->user($role);
        Sanctum::actingAs($u);

        return $u;
    }

    private function idDosen(User $u): int
    {
        $id = DB::table('dosen')->where('id_user', $u->id_user)->value('id_dosen');

        return (int) ($id ?: DB::table('dosen')->insertGetId([
            'id_user' => $u->id_user, 'nip' => 'N'.random_int(100000, 999999), 'nama' => 'Dosen Uji',
            'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function idMahasiswa(User $u): int
    {
        $id = DB::table('mahasiswa')->where('id_user', $u->id_user)->value('id_mahasiswa');

        return (int) ($id ?: DB::table('mahasiswa')->insertGetId([
            'id_user' => $u->id_user, 'nim' => 'M'.random_int(100000, 999999), 'nama' => 'Mahasiswa Uji',
            'tahun_masuk' => 2023, 'created_at' => now(), 'updated_at' => now(),
        ]));
    }

    private function kegiatan(User $pemilik, string $status, string $nama = 'Kegiatan Uji'): Kegiatan
    {
        $k = Kegiatan::query()->create(['id_kategori' => $this->kategori, 'id_user' => $pemilik->id_user, 'nama_kegiatan' => $nama]);
        Timeline::query()->create(['id_kegiatan' => $k->id_kegiatan, 'status' => 'diajukan', 'tanggal_status' => now()->subDays(2)]);
        Timeline::query()->create(['id_kegiatan' => $k->id_kegiatan, 'status' => $status, 'tanggal_status' => now()->subDay()]);

        return $k;
    }

    private function payload(array $ubah = []): array
    {
        return $ubah + [
            'jenis_kegiatan' => 'Kegiatan Prodi',
            'id_kategori' => $this->kategori,
            'nama_kegiatan' => 'Workshop Kurikulum',
            'penyelenggara' => 'Prodi TRK',
            'tanggal_mulai' => '2026-10-05',
            'tanggal_selesai' => '2026-10-06',
            'anggota' => ['Dosen:'.$this->idDosen($this->user(3))],
        ];
    }

    public function test_tanpa_token_ditolak_dan_dosen_tidak_boleh(): void
    {
        $this->getJson('/api/v1/admin/kegiatan')->assertStatus(401);

        $this->masuk(3);
        $this->getJson('/api/v1/admin/kegiatan')->assertStatus(403);
    }

    public function test_daftar_hanya_status_terakhir_diterima_dan_jenis_dari_role(): void
    {
        $this->kegiatan($this->user(3), 'diterima', 'Kegiatan Dosen A');
        $this->kegiatan($this->user(4), 'diterima', 'Kegiatan Mahasiswa A');
        $this->kegiatan($this->user(1), 'diterima', 'Kegiatan Prodi A');
        $this->kegiatan($this->user(3), 'direvisi', 'Kegiatan Direvisi');
        $this->masuk(2);

        $r = $this->getJson('/api/v1/admin/kegiatan')->assertOk();
        $this->assertSame(
            ['Kegiatan Dosen A', 'Kegiatan Mahasiswa A', 'Kegiatan Prodi A'],
            collect($r->json('data'))->pluck('nama')->sort()->values()->all()
        );
        $this->assertSame('dosen', collect($r->json('data'))->firstWhere('nama', 'Kegiatan Dosen A')['jenis']);
        $this->assertSame('prodi', collect($r->json('data'))->firstWhere('nama', 'Kegiatan Prodi A')['jenis']);

        $this->getJson('/api/v1/admin/kegiatan?jenis=mahasiswa')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/admin/kegiatan/ringkasan')->assertOk()
            ->assertJsonPath('data.jumlah.semua', 3)->assertJsonPath('data.jumlah.dosen', 1);
    }

    public function test_detail_memuat_anggota_dokumen_dan_riwayat(): void
    {
        $dosen = $this->user(3);
        $k = $this->kegiatan($dosen, 'diterima');
        AnggotaKegiatan::query()->create(['id_kegiatan' => $k->id_kegiatan, 'id_user' => $dosen->id_user, 'peran' => 'ketua']);
        DB::table('dokumen')->insert([
            'id_jenis_dokumen' => $this->jenisDokumen, 'id_kegiatan' => $k->id_kegiatan, 'nama_file' => 'Surat.pdf',
            'tipe_dokumen' => 'pdf', 'link_dokumen' => 'https://contoh.test/surat.pdf', 'is_lampiran' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->masuk(1);

        $r = $this->getJson('/api/v1/admin/kegiatan/'.$k->id_kegiatan)->assertOk();
        $r->assertJsonPath('data.anggota.0.status', 'Ketua')->assertJsonPath('data.anggota.0.pengaju', true);
        $r->assertJsonPath('data.dokumen.0.nama', 'Surat.pdf')
            ->assertJsonPath('data.dokumen.0.kategori', 'Surat tugas uji')
            ->assertJsonPath('data.dokumen.0.url', 'https://contoh.test/surat.pdf');
        $r->assertJsonCount(2, 'data.riwayat')->assertJsonPath('data.riwayat.1.status', 'Diterima');
    }

    public function test_detail_kegiatan_belum_diterima_404(): void
    {
        $k = $this->kegiatan($this->user(3), 'direvisi');
        $this->masuk(1);

        $this->getJson('/api/v1/admin/kegiatan/'.$k->id_kegiatan)->assertStatus(404);
    }

    public function test_admin_tambah_kegiatan_prodi_langsung_diterima(): void
    {
        $admin = $this->masuk(1);

        $r = $this->postJson('/api/v1/admin/kegiatan', $this->payload())->assertCreated();
        $r->assertJsonPath('data.jenis', 'prodi')->assertJsonPath('data.pengaju', $admin->email)->assertJsonPath('data.status', 'Disetujui');
        $r->assertJsonPath('data.anggota.0.peran', 'anggota');
        // kategori uji tidak punya isian tanggal_selesai -> peringatan, bukan galat
        $this->assertCount(1, $r->json('peringatan'));

        $id = $r->json('data.id');
        $this->assertSame('diterima', Kegiatan::query()->findOrFail($id)->statusTerkini->status);
        $this->getJson('/api/v1/admin/kegiatan?jenis=prodi')->assertJsonCount(1, 'data');
    }

    public function test_tambah_dinamis_memeriksa_master_terbaru_dan_menyimpan_jawaban_berulang(): void
    {
        $lokasi = DB::table('isian_kategori')->insertGetId([
            'id_kategori' => $this->kategori, 'input' => 'Lokasi', 'jenis_input' => 'teks',
            'aturan' => 'NOT NULL', 'jumlah_isian' => 0,
        ]);
        $this->masuk(2);
        $payload = [
            'isian_dinamis' => 1,
            'id_kategori' => $this->kategori,
            'nama_kegiatan' => 'Kegiatan dinamis',
        ];

        $this->postJson('/api/v1/admin/kegiatan', $payload)->assertUnprocessable()->assertJsonValidationErrors("detail.$lokasi");
        $this->postJson('/api/v1/admin/kegiatan', $payload + ['detail' => [999999 => ['Lokasi lain']]])
            ->assertUnprocessable()->assertJsonValidationErrors('detail');
        $this->assertDatabaseMissing('kegiatan', ['nama_kegiatan' => 'Kegiatan dinamis']);

        $hasil = $this->postJson('/api/v1/admin/kegiatan', $payload + [
            "detail[$lokasi][0]" => 'Semarang',
            "detail[$lokasi][2]" => 'Kendal',
        ])->assertCreated()->assertJsonPath('data.jenis', 'prodi')->assertJsonPath('peringatan', []);
        $id = $hasil->json('data.id');
        foreach (['Semarang', 'Kendal'] as $nilai) {
            $this->assertDatabaseHas('detail_kegiatan', ['id_kegiatan' => $id, 'id_isian_kategori' => $lokasi, 'isian' => $nilai]);
        }
        $this->assertSame(2, DB::table('detail_kegiatan')->where('id_kegiatan', $id)->count());
    }

    public function test_tambah_dinamis_menyimpan_berkas_isian(): void
    {
        $bukti = DB::table('isian_kategori')->insertGetId([
            'id_kategori' => $this->kategori, 'input' => 'Bukti', 'jenis_input' => 'berkas',
            'aturan' => 'NOT NULL', 'jumlah_isian' => 1,
        ]);
        $this->masuk(1);
        $payload = ['isian_dinamis' => 1, 'id_kategori' => $this->kategori, 'nama_kegiatan' => 'Kegiatan dengan berkas'];
        $this->postJson('/api/v1/admin/kegiatan', $payload + ['detail' => [$bukti => ['bukti.pdf']]])
            ->assertUnprocessable()->assertJsonValidationErrors("detail.$bukti.0");

        $hasil = $this->post('/api/v1/admin/kegiatan', $payload + [
            'detail' => [$bukti => [UploadedFile::fake()->create('bukti.pdf', 10, 'application/pdf')]],
        ], ['Accept' => 'application/json'])->assertCreated();
        $path = DB::table('detail_kegiatan')->where('id_kegiatan', $hasil->json('data.id'))
            ->where('id_isian_kategori', $bukti)->value('isian');
        Storage::disk('public')->assertExists($path);
    }

    public function test_form_dinamis_mengikuti_isian_master_dan_mencatat_pemilik_yang_dipilih(): void
    {
        $this->masuk(2);
        $dosen = $this->user(3);
        $mahasiswa = $this->user(4);
        $jenis = JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Mahasiswa'])->getKey();
        DB::table('kategori')->where('id_kategori', $this->kategori)->update(['id_jenis_kegiatan' => $jenis]);
        $fields = [];
        foreach (['members', 'documents', 'organizer'] as $nama) {
            $fields[$nama] = DB::table('isian_kategori')->insertGetId([
                'id_kategori' => $this->kategori, 'input' => $nama, 'jenis_input' => 'teks',
                'aturan' => $nama === 'organizer' ? 'NULL' : 'NOT NULL', 'jumlah_isian' => 1,
            ]);
        }
        $payload = ['isian_dinamis' => 1, 'id_kategori' => $this->kategori, 'nama_kegiatan' => 'Kegiatan sesuai master'];
        $endpoint = '/api/v1/admin/kegiatan';
        $this->postJson($endpoint, $payload + ['pemilik' => 'Dosen:'.$this->idDosen($dosen)])
            ->assertUnprocessable()->assertJsonValidationErrors('pemilik');
        $payload['pemilik'] = 'Mahasiswa:'.$this->idMahasiswa($mahasiswa);
        $this->postJson($endpoint, $payload + ['anggota' => [$payload['pemilik']], 'dokumen' => [['nama' => 'Di luar master']]])
            ->assertUnprocessable()->assertJsonValidationErrors(['anggota', 'dokumen']);
        $this->postJson($endpoint, $payload)->assertUnprocessable()->assertJsonValidationErrors("detail.{$fields['members']}");
        $this->assertDatabaseMissing('kegiatan', ['nama_kegiatan' => $payload['nama_kegiatan']]);

        $detail = [
            $fields['members'] => ['Anggota uji'],
            $fields['documents'] => ['Catatan dokumen'],
            $fields['organizer'] => ['Panitia uji'],
        ];
        $id = $this->postJson($endpoint, $payload + ['detail' => $detail])
            ->assertCreated()->assertJsonPath('data.jenis', 'mahasiswa')->json('data.id');
        $this->assertDatabaseHas('anggota_kegiatan', ['id_kegiatan' => $id, 'id_user' => $mahasiswa->getKey(), 'peran' => 'ketua']);
        $this->assertDatabaseHas('detail_kegiatan', ['id_kegiatan' => $id, 'id_isian_kategori' => $fields['documents'], 'isian' => 'Catatan dokumen']);
        $this->assertDatabaseHas('detail_kegiatan', ['id_kegiatan' => $id, 'id_isian_kategori' => $fields['organizer'], 'isian' => 'Panitia uji']);
        $this->assertDatabaseMissing('dokumen', ['id_kegiatan' => $id]);
    }

    #[TestWith(['Kegiatan Prodi', 'prodi'])]
    #[TestWith(['Kegiatan Mahasiswa', 'mahasiswa'])]
    public function test_peserta_dua_angkatan_dan_peserta_manual_disimpan_tanpa_duplikat(string $jenis, string $tab): void
    {
        config(['admin.prodi.kode' => 'TRK-UJI']);
        $kaprodi = $this->masuk(2);
        $dosen = $this->user(3);
        $prodi = DB::table('prodi')->insertGetId(['kode_prodi' => 'TRK-UJI', 'nama_prodi' => 'Prodi Uji', 'jenjang' => 'D4']);
        $prodiLain = DB::table('prodi')->insertGetId(['kode_prodi' => 'LAIN-UJI', 'nama_prodi' => 'Prodi Lain', 'jenjang' => 'D4']);
        $tahunAkademik = DB::table('tahun_akademik')->insertGetId(['tahun_akademik' => 'Ganjil 2025/2026']);
        $rombel = fn ($prodiId) => DB::table('enrollment_tingkat_kelas')->insertGetId([
            'id_prodi' => $prodiId, 'id_tahun_akademik' => $tahunAkademik,
            'id_kelas' => DB::table('kelas')->value('id_kelas'), 'id_tingkat' => DB::table('tingkat')->value('id_tingkat'),
        ]);
        $kelas = $rombel($prodi);
        $kelasLain = $rombel($prodiLain);
        $mahasiswa = function (int $tahun, int $kelas, string $status = 'Aktif'): array {
            $user = User::create(['id_role' => 4, 'email' => uniqid().'@test.local', 'password' => 'password', 'status' => true]);
            $id = DB::table('mahasiswa')->insertGetId([
                'id_user' => $user->getKey(), 'nim' => uniqid(), 'nama' => 'Mahasiswa Uji', 'tahun_masuk' => $tahun, 'status_mahasiswa' => $status,
            ]);
            DB::table('enrollment_mahasiswa_tingkat_kelas')->insert([
                'id_mahasiswa' => $id, 'id_enrollment_tingkat_kelas' => $kelas,
            ]);

            return [$user->getKey(), $id];
        };
        [$angkatan24, $idMahasiswa24] = $mahasiswa(2024, $kelas);
        [$angkatan25] = $mahasiswa(2025, $kelas);
        [$manual, $idManual] = $mahasiswa(2026, $kelas);
        [$cuti] = $mahasiswa(2025, $kelas, 'Cuti');
        [$lain] = $mahasiswa(2025, $kelasLain);
        $jenisId = JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => $jenis])->getKey();
        DB::table('kategori')->where('id_kategori', $this->kategori)->update(['id_jenis_kegiatan' => $jenisId]);
        $field = DB::table('isian_kategori')->insertGetId([
            'id_kategori' => $this->kategori, 'input' => 'Anggota Kegiatan', 'jenis_input' => 'anggota_kegiatan',
            'aturan' => 'NOT NULL', 'jumlah_isian' => 1,
        ]);
        $payload = ['isian_dinamis' => 1, 'id_kategori' => $this->kategori, 'nama_kegiatan' => 'Kuliah tamu uji'];
        $pemilik = $kaprodi->getKey();
        $anggota = [$angkatan24, $angkatan25, $manual, $dosen->getKey()];
        if ($tab === 'mahasiswa') {
            $payload['pemilik'] = "Mahasiswa:$idMahasiswa24";
            $pemilik = $angkatan24;
            $anggota = [$angkatan25, $manual, $dosen->getKey()];
        }
        $url = '/api/v1/admin/kegiatan';
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors("detail.$field");
        $this->postJson($url, $payload + ['detail' => [$field => ['Angkatan:2099']]])
            ->assertUnprocessable()->assertJsonValidationErrors("detail.$field");
        $this->postJson($url, $payload + ['detail' => [$field => ['SemuaDosen']]])
            ->assertUnprocessable()->assertJsonValidationErrors("detail.$field");
        $id = $this->postJson($url, $payload + ['detail' => [$field => [
            'Angkatan:2024', 'Angkatan:2025', "Mahasiswa:$idMahasiswa24", "Mahasiswa:$idManual", 'Dosen:'.$this->idDosen($dosen),
        ]]])->assertCreated()->assertJsonPath('data.jenis', $tab)->json('data.id');

        $this->assertDatabaseHas('kegiatan', ['id_kegiatan' => $id, 'id_user' => $pemilik]);
        $this->assertEqualsCanonicalizing($anggota, DB::table('anggota_kegiatan')
            ->where('id_kegiatan', $id)->where('peran', 'anggota')->pluck('id_user')->all());
        if ($tab === 'mahasiswa') {
            $this->assertDatabaseHas('anggota_kegiatan', ['id_kegiatan' => $id, 'id_user' => $pemilik, 'peran' => 'ketua']);
        }
        $this->assertDatabaseMissing('anggota_kegiatan', ['id_kegiatan' => $id, 'id_user' => $cuti]);
        $this->assertDatabaseMissing('anggota_kegiatan', ['id_kegiatan' => $id, 'id_user' => $lain]);
    }

    public function test_kegiatan_dosen_memilih_semua_dosen_aktif_dan_pemilik_tetap_ketua(): void
    {
        $this->masuk(1);
        $pemilik = $this->user(3);
        $dosenAktif = User::create(['id_role' => 3, 'email' => 'dosen-aktif@test.local', 'password' => 'password', 'status' => true]);
        $dosenNonaktif = User::create(['id_role' => 3, 'email' => 'dosen-nonaktif@test.local', 'password' => 'password', 'status' => false]);
        $idAktif = $this->idDosen($dosenAktif);
        $this->idDosen($dosenNonaktif);
        $jenis = JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Dosen'])->getKey();
        DB::table('kategori')->where('id_kategori', $this->kategori)->update(['id_jenis_kegiatan' => $jenis]);
        $field = DB::table('isian_kategori')->insertGetId([
            'id_kategori' => $this->kategori, 'input' => 'Anggota Kegiatan', 'jenis_input' => 'teks', 'aturan' => 'NOT NULL',
        ]);
        $payload = [
            'isian_dinamis' => 1, 'id_kategori' => $this->kategori, 'nama_kegiatan' => 'Kegiatan dosen uji',
            'pemilik' => 'Dosen:'.$this->idDosen($pemilik),
        ];
        $url = '/api/v1/admin/kegiatan';
        $this->postJson($url, $payload + ['detail' => [$field => ['Angkatan:2025']]])
            ->assertUnprocessable()->assertJsonValidationErrors("detail.$field");
        $id = $this->postJson($url, $payload + ['detail' => [$field => ['SemuaDosen', "Dosen:$idAktif"]]])
            ->assertCreated()->assertJsonPath('data.jenis', 'dosen')->json('data.id');

        $this->assertEqualsCanonicalizing([
            [$pemilik->getKey(), 'ketua'],
            [$dosenAktif->getKey(), 'anggota'],
        ], DB::table('anggota_kegiatan')->where('id_kegiatan', $id)->get(['id_user', 'peran'])
            ->map(fn ($row) => [$row->id_user, $row->peran])->all());
    }

    #[TestWith([1, 'Kegiatan Dosen', 'dosen'])]
    #[TestWith([2, 'Kegiatan Dosen', 'dosen'])]
    #[TestWith([1, 'Kegiatan Mahasiswa', 'mahasiswa'])]
    #[TestWith([2, 'Kegiatan Mahasiswa', 'mahasiswa'])]
    public function test_tanpa_pemilik_akun_pencatat_dan_tab_mengikuti_kategori(int $role, string $jenis, string $tab): void
    {
        $pencatat = $this->masuk($role);
        $peserta = $this->user($tab === 'dosen' ? 3 : 4);
        $pilihan = $tab === 'dosen' ? 'Dosen:'.$this->idDosen($peserta) : 'Mahasiswa:'.$this->idMahasiswa($peserta);
        $idJenis = JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => $jenis])->getKey();
        DB::table('kategori')->where('id_kategori', $this->kategori)->update(['id_jenis_kegiatan' => $idJenis]);
        $field = DB::table('isian_kategori')->insertGetId([
            'id_kategori' => $this->kategori, 'input' => 'Anggota Kegiatan', 'jenis_input' => 'teks', 'aturan' => 'NOT NULL',
        ]);
        $url = '/api/v1/admin/kegiatan';
        $payload = ['isian_dinamis' => 1, 'id_kategori' => $this->kategori, 'nama_kegiatan' => 'Kegiatan tanpa pemilik'];

        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors("detail.$field");

        $id = $this->postJson($url, $payload + ['detail' => [$field => [$pilihan]]])
            ->assertCreated()
            ->assertJsonPath('data.jenis', $tab)
            ->assertJsonPath('data.dicatat_tanpa_pemilik', true)
            ->assertJsonPath('data.pengaju', $pencatat->email)
            ->assertJsonCount(1, 'data.anggota')
            ->assertJsonPath('data.anggota.0.peran', 'anggota')
            ->json('data.id');

        $this->assertDatabaseHas('kegiatan', ['id_kegiatan' => $id, 'id_user' => $pencatat->getKey()]);
        $this->assertDatabaseMissing('anggota_kegiatan', ['id_kegiatan' => $id, 'id_user' => $pencatat->getKey()]);
        $this->getJson("$url?jenis=$tab")->assertOk()->assertJsonFragment(['id' => $id, 'jenis' => $tab]);
        $this->getJson("$url?jenis=prodi")->assertOk()->assertJsonMissing(['id' => $id]);
        $this->getJson("$url/ringkasan")->assertOk()->assertJsonPath("data.jumlah.$tab", 1);
    }

    public function test_kaprodi_tambah_kegiatan_dosen_pemilik_dosen_pertama(): void
    {
        $this->masuk(2);
        $dosen = $this->user(3);

        $r = $this->postJson('/api/v1/admin/kegiatan', $this->payload([
            'jenis_kegiatan' => 'Kegiatan Dosen',
            'anggota' => ['Dosen:'.$this->idDosen($dosen), 'Mahasiswa:'.$this->idMahasiswa($this->user(4))],
        ]))->assertCreated();

        $r->assertJsonPath('data.jenis', 'dosen');
        $this->assertSame((int) $dosen->id_user, (int) Kegiatan::query()->findOrFail($r->json('data.id'))->id_user);
        $this->assertSame('ketua', collect($r->json('data.anggota'))->firstWhere('id_user', $dosen->id_user)['peran']);
    }

    public function test_tambah_dengan_dokumen_file_dan_tautan_lewat_kunci_berkurung(): void
    {
        $this->masuk(1);

        $r = $this->post('/api/v1/admin/kegiatan', $this->payload([
            'dokumen' => [
                ['nama' => 'Surat tugas', 'jenis' => 'Surat tugas uji', 'file' => UploadedFile::fake()->create('s.pdf', 50, 'application/pdf')],
                ['nama' => 'Folder foto', 'jenis' => 'Surat tugas uji', 'tautan' => 'https://contoh.test/foto'],
            ],
        ]), ['Accept' => 'application/json'])->assertCreated();

        $r->assertJsonCount(2, 'data.dokumen');
        $urls = collect($r->json('data.dokumen'))->pluck('url');
        $this->assertTrue($urls->contains('https://contoh.test/foto'));
        $this->assertCount(1, Storage::disk('public')->allFiles('kegiatan'));
    }

    public function test_validasi_dan_rollback(): void
    {
        $this->masuk(1);

        $this->postJson('/api/v1/admin/kegiatan', $this->payload(['anggota' => []]))->assertStatus(422);
        $this->postJson('/api/v1/admin/kegiatan', $this->payload(['tanggal_selesai' => '2026-10-01']))->assertStatus(422);
        $this->postJson('/api/v1/admin/kegiatan', $this->payload(['id_kategori' => 999999]))->assertStatus(422);
        // Kegiatan Mahasiswa tanpa anggota mahasiswa
        $this->postJson('/api/v1/admin/kegiatan', $this->payload(['jenis_kegiatan' => 'Kegiatan Mahasiswa']))->assertStatus(422);
        // jenis dokumen tidak dikenal
        $this->postJson('/api/v1/admin/kegiatan', $this->payload([
            'dokumen' => [['nama' => 'X', 'jenis' => 'Tidak ada', 'tautan' => 'https://contoh.test/x']],
        ]))->assertStatus(422);

        $this->assertSame(0, Kegiatan::query()->count());
    }

    public function test_kaprodi_tidak_boleh_hapus_admin_boleh(): void
    {
        $k = $this->kegiatan($this->user(3), 'diterima');

        $this->masuk(2);
        $this->deleteJson('/api/v1/admin/kegiatan/'.$k->id_kegiatan)->assertStatus(403);

        $this->masuk(1);
        $this->deleteJson('/api/v1/admin/kegiatan/'.$k->id_kegiatan)->assertOk();
        $this->assertNull(Kegiatan::query()->find($k->id_kegiatan));
    }
}
