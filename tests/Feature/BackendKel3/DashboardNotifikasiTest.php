<?php

namespace Tests\Feature\BackendKel3;

use App\Models\Dosen;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Role;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tes area Ilham: dashboard dosen dan notifikasi (app/BackendKel3/Dashboard).
 *
 * Empat kriteria "selesai" pada Dashboard/README.md:
 *   1. kartu dan daftar cocok untuk dosen A
 *   2. keduanya kosong untuk dosen baru
 *   3. perubahan status milik dosen B tidak bocor
 *   4. tautan notifikasi membuka pengajuan yang tepat
 * ditambah: status "dibaca" tetap tersimpan setelah dimuat ulang.
 */
class DashboardNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private const DASHBOARD = '/api/v1/dosen/dashboard';

    private const NOTIFIKASI = '/api/v1/dosen/notifikasi';

    private int $urut = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    // ------------------------------------------------------------------
    // Alat bantu
    // ------------------------------------------------------------------

    private function akun(string $role, string $email): User
    {
        return User::create([
            'id_role' => Role::firstOrCreate(['nama_role' => $role])->getKey(),
            'email' => $email,
            'password' => 'sandi-uji',
            'status' => true,
        ]);
    }

    private function dosen(string $nama): User
    {
        $user = $this->akun('dosen', str_replace(' ', '.', strtolower($nama)).'@example.test');
        Dosen::create([
            'id_user' => $user->getKey(),
            'nip' => '1980'.str_pad((string) ++$this->urut, 6, '0', STR_PAD_LEFT),
            'nama' => $nama,
        ]);

        return $user;
    }

    /** Berganti pengguna di tengah tes tanpa sisa sesi dari pengguna sebelumnya. */
    private function sebagai(User $user): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
    }

    private function kategori(string $nama): Kategori
    {
        $jenis = JenisKegiatan::firstOrCreate(['nama_jenis_kegiatan' => 'Kegiatan Dosen']);

        return Kategori::firstOrCreate(['id_jenis_kegiatan' => $jenis->getKey(), 'nama_kategori' => $nama]);
    }

    private function kegiatan(User $pemilik, string $kategori, string $nama, ?string $status = null): Kegiatan
    {
        $kegiatan = Kegiatan::create([
            'id_kategori' => $this->kategori($kategori)->getKey(),
            'id_user' => $pemilik->getKey(),
            'nama_kegiatan' => $nama,
        ]);

        if ($status) {
            $this->tambahStatus($kegiatan, $status);
        }

        return $kegiatan;
    }

    private function tambahStatus(Kegiatan $kegiatan, string $status, $waktu = null, ?string $catatan = null): Timeline
    {
        return Timeline::create([
            'id_kegiatan' => $kegiatan->getKey(),
            'status' => $status,
            'tanggal_status' => $waktu ?? now(),
            'catatan_revisi' => $catatan,
        ]);
    }

    private function jadikanAnggota(Kegiatan $kegiatan, User $user): void
    {
        DB::table('anggota_kegiatan')->insert([
            'id_kegiatan' => $kegiatan->getKey(),
            'id_user' => $user->getKey(),
            'peran' => 'anggota',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function idTimeline(Kegiatan $kegiatan): int
    {
        return (int) Timeline::where('id_kegiatan', $kegiatan->getKey())->value('id_timeline');
    }

    /**
     * Dosen A: 4 kegiatan miliknya + 1 kegiatan milik B tempat ia menjadi anggota.
     *   penelitian : diajukan, direvisi, draf (belum diajukan)  -> 3
     *   pengabdian : diterima + milik B (anggota, direview)     -> 2
     *   menunggu   : diajukan, direvisi, direview               -> 3 (draf dan diterima TIDAK ikut)
     *   perlu revisi                                            -> 1
     *   total                                                   -> 5
     * Dosen B juga punya satu kegiatan rahasia yang tidak boleh terlihat oleh A.
     *
     * @return array{a: User, b: User, k: array<string, Kegiatan>}
     */
    private function skenario(): array
    {
        $a = $this->dosen('Dosen A');
        $b = $this->dosen('Dosen B');

        $k = [
            'p_diajukan' => $this->kegiatan($a, 'Penelitian', 'Riset Diajukan', 'diajukan'),
            'p_revisi' => $this->kegiatan($a, 'Penelitian', 'Riset Revisi', 'direvisi'),
            'p_draf' => $this->kegiatan($a, 'Penelitian', 'Riset Draf'),
            'pg_diterima' => $this->kegiatan($a, 'Pengabdian', 'Abdimas Diterima', 'diterima'),
            'b_milik' => $this->kegiatan($b, 'Pengabdian', 'Abdimas Milik B', 'direview'),
            'b_rahasia' => $this->kegiatan($b, 'Penelitian', 'Riset Rahasia B', 'diajukan'),
        ];
        $this->jadikanAnggota($k['b_milik'], $a);

        return compact('a', 'b', 'k');
    }

    // ------------------------------------------------------------------
    // Kriteria 1: kartu dan daftar cocok untuk dosen A
    // ------------------------------------------------------------------

    public function test_kartu_dan_daftar_dashboard_dosen_a_cocok(): void
    {
        ['a' => $a, 'k' => $k] = $this->skenario();
        $this->sebagai($a);

        $res = $this->getJson(self::DASHBOARD)->assertOk()->assertJsonPath('success', true);

        $res->assertJsonPath('data.ringkasan.total_kegiatan', 5)
            ->assertJsonPath('data.ringkasan.penelitian', 3)
            ->assertJsonPath('data.ringkasan.pengabdian', 2)
            ->assertJsonPath('data.ringkasan.pengajuan_menunggu', 3)
            ->assertJsonPath('data.ringkasan.perlu_revisi', 1)
            ->assertJsonPath('data.ringkasan.penugasan_mengajar', 0);

        $idMenunggu = collect($res->json('data.daftar_menunggu'))->pluck('id_kegiatan')->sort()->values()->all();
        $diharapkan = collect([$k['p_diajukan'], $k['p_revisi'], $k['b_milik']])->map->getKey()->sort()->values()->all();

        $this->assertSame($diharapkan, $idMenunggu, 'Daftar menunggu harus berisi tepat kegiatan diajukan/direvisi/direview.');
        $this->assertSame(count($idMenunggu), $res->json('data.ringkasan.pengajuan_menunggu'), 'Angka kartu harus sama dengan panjang daftar.');
        $this->assertNotContains($k['p_draf']->getKey(), $idMenunggu, 'Draf (belum punya timeline) tidak boleh dianggap menunggu.');
        $this->assertNotContains($k['pg_diterima']->getKey(), $idMenunggu);

        $grafik = $res->json('data.grafik_per_tahun');
        $this->assertCount(7, $grafik);
        $this->assertSame(5, array_sum($grafik), 'Semua kegiatan harus masuk salah satu tahun pada grafik.');
    }

    public function test_halaman_web_menampilkan_angka_yang_sama_dengan_api(): void
    {
        ['a' => $a] = $this->skenario();

        $this->actingAs($a)->get('/dosen/dashboard')->assertOk()
            ->assertViewHas('stats', function ($stats) {
                $nilai = array_column($stats, 'value', 'label');

                return $nilai['Total Kegiatan'] === 5 && $nilai['Penelitian'] === 3
                    && $nilai['Pengabdian'] === 2 && $nilai['Pengajaran'] === 0
                    && $nilai['Menunggu Verifikasi'] === 3 && $nilai['Perlu Revisi'] === 1;
            })
            ->assertViewHas('menunggu', fn ($m) => count($m) === 3);
    }

    // ------------------------------------------------------------------
    // Kriteria 2: dosen baru -> kosong
    // ------------------------------------------------------------------

    public function test_dosen_baru_melihat_dashboard_dan_notifikasi_kosong(): void
    {
        $this->skenario(); // data dosen lain ada, tetapi bukan milik dosen baru
        $baru = $this->dosen('Dosen Baru');
        $this->sebagai($baru);

        $res = $this->getJson(self::DASHBOARD)->assertOk();
        foreach (['total_kegiatan', 'penelitian', 'pengabdian', 'pengajuan_menunggu', 'perlu_revisi', 'penugasan_mengajar'] as $kunci) {
            $res->assertJsonPath("data.ringkasan.$kunci", 0);
        }
        $this->assertSame([], $res->json('data.daftar_menunggu'));
        $this->assertSame(0, array_sum($res->json('data.grafik_per_tahun')));

        $this->getJson(self::NOTIFIKASI)->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Daftar notifikasi berhasil dimuat.',
            'data' => [],
        ]);
    }

    public function test_dosen_baru_melihat_halaman_web_kosong(): void
    {
        $this->skenario();
        $baru = $this->dosen('Dosen Baru');

        $this->actingAs($baru)->get('/dosen/dashboard')->assertOk()
            ->assertViewHas('stats', fn ($s) => collect($s)->every(fn ($kartu) => $kartu['value'] === 0))
            ->assertViewHas('menunggu', fn ($m) => $m === []);

        $this->get('/notifikasi')->assertOk()
            ->assertViewHas('notifikasi', fn ($n) => $n === []);
    }

    // ------------------------------------------------------------------
    // Kriteria 3: data dosen B tidak bocor
    // ------------------------------------------------------------------

    public function test_data_dosen_lain_tidak_bocor(): void
    {
        ['a' => $a, 'k' => $k] = $this->skenario();
        $this->sebagai($a);

        $judul = collect($this->getJson(self::NOTIFIKASI)->assertOk()->json('data'))->pluck('judul_kegiatan')->all();
        $this->assertNotContains('Riset Rahasia B', $judul);
        $this->assertContains('Abdimas Milik B', $judul, 'A anggota kegiatan ini, jadi boleh melihatnya.');

        $dashboard = $this->getJson(self::DASHBOARD)->assertOk()->getContent();
        $this->assertStringNotContainsString('Riset Rahasia B', $dashboard);

        // Tidak bisa menandai (atau bahkan memastikan keberadaan) notifikasi milik B.
        $this->postJson(self::NOTIFIKASI.'/'.$this->idTimeline($k['b_rahasia']).'/baca')->assertNotFound();
        $this->assertDatabaseCount('notifikasi_dibaca', 0);
    }

    public function test_hanya_dosen_aktif_yang_boleh_mengakses(): void
    {
        $this->getJson(self::DASHBOARD)->assertUnauthorized();
        $this->getJson(self::NOTIFIKASI)->assertUnauthorized();

        $this->sebagai($this->akun('mahasiswa', 'mhs@example.test'));
        $this->getJson(self::DASHBOARD)->assertForbidden();
        $this->getJson(self::NOTIFIKASI)->assertForbidden();
        $this->postJson(self::NOTIFIKASI.'/baca-semua')->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Kriteria 4: tautan membuka pengajuan yang tepat
    // ------------------------------------------------------------------

    public function test_tautan_notifikasi_menunjuk_kegiatan_yang_tepat(): void
    {
        ['a' => $a, 'k' => $k] = $this->skenario();
        $this->sebagai($a);

        $data = collect($this->getJson(self::NOTIFIKASI)->assertOk()->json('data'));

        foreach ($data as $n) {
            $this->assertSame(route('dosen.kegiatan.show', $n['id_kegiatan']), $n['tautan_detail']);
        }

        $riset = $data->firstWhere('judul_kegiatan', 'Riset Diajukan');
        $this->assertSame($k['p_diajukan']->getKey(), $riset['id_kegiatan']);
        $this->assertSame($this->idTimeline($k['p_diajukan']), $riset['id_timeline']);

        // Tautan pada daftar "menunggu" di dashboard juga memakai id kegiatan yang sama.
        foreach ($this->getJson(self::DASHBOARD)->json('data.daftar_menunggu') as $baris) {
            $this->assertSame(route('dosen.kegiatan.show', $baris['id_kegiatan']), $baris['detail_url']);
        }
    }

    public function test_tautan_notifikasi_dapat_dibuka_pemilik_dan_ditolak_untuk_orang_lain(): void
    {
        ['a' => $a, 'b' => $b, 'k' => $k] = $this->skenario();

        // Halaman detail membaca tanggal dari isian kegiatan, jadi sediakan satu isian seperti di FrontendLayoutTest.
        $isian = DB::table('isian_kategori')->insertGetId([
            'id_kategori' => $k['p_diajukan']->id_kategori, 'input' => 'tanggal_mulai', 'jenis_input' => 'tanggal',
        ], 'id_isian_kategori');
        DB::table('detail_kegiatan')->insert([
            'id_kegiatan' => $k['p_diajukan']->getKey(), 'id_isian_kategori' => $isian, 'isian' => now()->toDateString(),
        ]);

        $this->actingAs($a);
        $this->get(route('dosen.kegiatan.show', $k['p_diajukan']->getKey()))->assertOk();
        $this->get(route('dosen.kegiatan.show', $k['b_rahasia']->getKey()))->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Status "dibaca" tersimpan (masalah dari koordinator)
    // ------------------------------------------------------------------

    public function test_notifikasi_yang_dibuka_tetap_terbaca_setelah_dimuat_ulang(): void
    {
        ['a' => $a, 'k' => $k] = $this->skenario();
        $this->sebagai($a);

        $awal = collect($this->getJson(self::NOTIFIKASI)->json('data'));
        $this->assertCount(4, $awal);
        $this->assertTrue($awal->every(fn ($n) => $n['belum_dibaca'] === true));

        $id = $this->idTimeline($k['p_diajukan']);
        $this->postJson(self::NOTIFIKASI."/$id/baca")->assertOk()->assertJsonPath('data.belum_dibaca', false);
        $this->postJson(self::NOTIFIKASI."/$id/baca")->assertOk(); // idempoten
        $this->assertDatabaseCount('notifikasi_dibaca', 1);

        // "Refresh": ambil ulang dari server.
        $ulang = collect($this->getJson(self::NOTIFIKASI)->json('data'));
        $this->assertFalse($ulang->firstWhere('id_timeline', $id)['belum_dibaca']);
        $this->assertSame(3, $ulang->where('belum_dibaca', true)->count());

        // Status baru pada kegiatan yang sama adalah notifikasi baru: belum dibaca.
        $this->tambahStatus($k['p_diajukan'], 'direview');
        $baru = collect($this->getJson(self::NOTIFIKASI)->json('data'))
            ->first(fn ($n) => $n['id_kegiatan'] === $k['p_diajukan']->getKey() && $n['status'] === 'direview');
        $this->assertNotNull($baru);
        $this->assertTrue($baru['belum_dibaca']);
    }

    public function test_tandai_semua_dibaca_tersimpan(): void
    {
        ['a' => $a] = $this->skenario();
        $this->sebagai($a);

        $this->postJson(self::NOTIFIKASI.'/baca-semua')->assertOk()->assertJsonPath('data.jumlah_ditandai', 4);
        $this->postJson(self::NOTIFIKASI.'/baca-semua')->assertOk(); // idempoten
        $this->assertDatabaseCount('notifikasi_dibaca', 4);

        $data = collect($this->getJson(self::NOTIFIKASI)->json('data'));
        $this->assertSame(0, $data->where('belum_dibaca', true)->count());
    }

    public function test_status_baca_dipisah_per_pengguna(): void
    {
        ['a' => $a, 'b' => $b, 'k' => $k] = $this->skenario();

        // B (pemilik) membuka notifikasi kegiatannya sendiri...
        $this->sebagai($b);
        $this->postJson(self::NOTIFIKASI.'/'.$this->idTimeline($k['b_milik']).'/baca')->assertOk();

        // ...tetapi A (anggota) tetap belum membukanya.
        $this->sebagai($a);
        $item = collect($this->getJson(self::NOTIFIKASI)->json('data'))->firstWhere('id_kegiatan', $k['b_milik']->getKey());
        $this->assertTrue($item['belum_dibaca']);
        $this->assertSame('tim', $item['jenis'], 'Bagi A, kegiatan milik B adalah notifikasi tim.');
    }

    public function test_notifikasi_lama_tidak_dihitung_belum_dibaca(): void
    {
        $a = $this->dosen('Dosen A');
        $kegiatan = $this->kegiatan($a, 'Penelitian', 'Riset Lama');
        $this->tambahStatus($kegiatan, 'diajukan', now()->subDays(60));
        $this->sebagai($a);

        $item = $this->getJson(self::NOTIFIKASI)->json('data.0');
        $this->assertFalse($item['belum_dibaca']);
    }

    public function test_halaman_web_menyimpan_dan_memuat_status_baca(): void
    {
        ['a' => $a, 'k' => $k] = $this->skenario();
        $this->actingAs($a);

        $id = $this->idTimeline($k['p_diajukan']);
        $this->get('/prestasi')->assertOk()->assertSee('x-data="{ n: 4 }"', false);
        $this->postJson("/notifikasi/$id/baca")->assertOk();
        $this->get('/prestasi')->assertOk()->assertSee('x-data="{ n: 3 }"', false);

        $this->get('/notifikasi')->assertOk()->assertViewHas('notifikasi', function ($n) use ($id) {
            $daftar = collect($n);

            return $daftar->firstWhere('id', $id)['unread'] === false
                && $daftar->where('unread', true)->count() === 3;
        });

        $this->postJson('/notifikasi/baca-semua')->assertOk();
        $this->get('/prestasi')->assertOk()->assertSee('x-data="{ n: 0 }"', false);
        $this->get('/notifikasi')->assertViewHas('notifikasi', fn ($n) => collect($n)->where('unread', true)->isEmpty());
    }
}
