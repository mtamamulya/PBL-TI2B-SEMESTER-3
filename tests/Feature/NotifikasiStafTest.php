<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NotifikasiStaf;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Fase 1 notifikasi: kontrak data + endpoint baca (mode dummy). Tampilan diuji di NotifikasiStafTampilanTest. */
class NotifikasiStafTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-27 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{0:string,1:int}> */
    public static function peran(): array
    {
        return ['admin' => ['admin', 1], 'kaprodi' => ['kaprodi', 2]];
    }

    private function masuk(string $prefix, int $idRole): static
    {
        DB::table('roles')->insertOrIgnore(['id_role' => $idRole, 'nama_role' => $prefix]);
        $user = User::firstOrCreate(
            ['email' => "{$prefix}@trk.ac.id"],
            ['id_role' => $idRole, 'password' => 'password', 'status' => true]
        );

        return $this->actingAs($user)->withSession([$prefix => ['nama' => ucfirst($prefix)]]);
    }

    /* ---------- kontrak data ---------- */

    #[DataProvider('peran')]
    public function test_setiap_item_memenuhi_kontrak(string $prefix): void
    {
        $daftar = NotifikasiStaf::daftar($prefix);

        $this->assertNotEmpty($daftar);
        foreach ($daftar as $n) {
            foreach (NotifikasiStaf::KUNCI_ITEM as $kunci) {
                $this->assertArrayHasKey($kunci, $n, "kunci {$kunci} hilang pada {$n['id']}");
            }
            $this->assertContains($n['type'], NotifikasiStaf::JENIS);
            $this->assertContains($n['g'], NotifikasiStaf::KELOMPOK);
            $this->assertIsBool($n['unread']);
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $n['id']);
        }
        $this->assertSame(count($daftar), count(array_unique(array_column($daftar, 'id'))), 'id harus unik');
    }

    #[DataProvider('peran')]
    public function test_urut_terbaru_di_atas_dan_ada_tiga_kelompok_waktu(string $prefix): void
    {
        $daftar = NotifikasiStaf::daftar($prefix);

        $waktu = array_column($daftar, 'waktu_iso');
        $urut = $waktu;
        rsort($urut);
        $this->assertSame($urut, $waktu);
        $this->assertEqualsCanonicalizing(NotifikasiStaf::KELOMPOK, array_values(array_unique(array_column($daftar, 'g'))));
    }

    public function test_admin_dan_kaprodi_punya_isi_berbeda(): void
    {
        $admin = NotifikasiStaf::semuaId('admin');
        $kaprodi = NotifikasiStaf::semuaId('kaprodi');

        $this->assertSame([], array_intersect($admin, $kaprodi), 'id tidak boleh bersilangan antar peran');
        // admin menerima berita yang diajukan; kaprodi hanya menerima hasil tinjauan berita miliknya
        $this->assertTrue(collect(NotifikasiStaf::daftar('admin'))->contains(fn ($n) => $n['type'] === 'berita' && str_contains($n['title'], 'menunggu tinjauan')));
        $this->assertTrue(collect(NotifikasiStaf::daftar('kaprodi'))->contains(fn ($n) => $n['type'] === 'berita' && str_contains($n['title'], 'Anda')));
    }

    public function test_status_dibaca_menurunkan_jumlah(): void
    {
        $semua = NotifikasiStaf::daftar('admin');
        $belum = array_values(array_filter($semua, fn ($n) => $n['unread']));
        $this->assertGreaterThan(1, count($belum));

        $setelah = NotifikasiStaf::daftar('admin', [$belum[0]['id']]);
        $this->assertCount(count($belum) - 1, array_filter($setelah, fn ($n) => $n['unread']));
    }

    /* ---------- endpoint ---------- */

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->post(route('admin.notifikasi.baca-semua'))->assertRedirect(route('login'));
        $this->post(route('kaprodi.notifikasi.baca', 'k-9011'))->assertRedirect(route('login'));
    }

    #[DataProvider('peran')]
    public function test_baca_satu_notifikasi_tersimpan_di_session(string $prefix, int $idRole): void
    {
        $id = NotifikasiStaf::semuaId($prefix)[0];

        $this->masuk($prefix, $idRole)
            ->postJson(route("{$prefix}.notifikasi.baca", $id))
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.belum_dibaca', false);

        $this->assertContains($id, session(NotifikasiStaf::kunciSession($prefix)));
    }

    #[DataProvider('peran')]
    public function test_baca_dua_kali_tidak_error_dan_tidak_ganda(string $prefix, int $idRole): void
    {
        $id = NotifikasiStaf::semuaId($prefix)[0];
        $akun = $this->masuk($prefix, $idRole);

        $akun->postJson(route("{$prefix}.notifikasi.baca", $id))->assertOk();
        $akun->postJson(route("{$prefix}.notifikasi.baca", $id))->assertOk();

        $this->assertSame(1, count(array_keys(session(NotifikasiStaf::kunciSession($prefix)), $id, true)));
    }

    public function test_id_tidak_dikenal_atau_milik_peran_lain_ditolak_404(): void
    {
        $idAdmin = NotifikasiStaf::semuaId('admin')[0];
        $idKaprodi = NotifikasiStaf::semuaId('kaprodi')[0];

        $this->masuk('kaprodi', 2)->postJson(route('kaprodi.notifikasi.baca', $idAdmin))->assertNotFound();
        $this->masuk('admin', 1)->postJson(route('admin.notifikasi.baca', $idKaprodi))->assertNotFound();
        $this->masuk('admin', 1)->postJson(route('admin.notifikasi.baca', 'ngawur-1'))->assertNotFound();
    }

    #[DataProvider('peran')]
    public function test_baca_semua_menandai_semua_yang_belum_dibaca(string $prefix, int $idRole): void
    {
        $belum = count(array_filter(NotifikasiStaf::daftar($prefix), fn ($n) => $n['unread']));
        $this->assertGreaterThan(0, $belum);

        $akun = $this->masuk($prefix, $idRole);
        $akun->postJson(route("{$prefix}.notifikasi.baca-semua"))
            ->assertOk()
            ->assertJsonPath('data.jumlah_ditandai', $belum);

        $dibaca = session(NotifikasiStaf::kunciSession($prefix));
        $this->assertSame(0, count(array_filter(NotifikasiStaf::daftar($prefix, $dibaca), fn ($n) => $n['unread'])));

        $akun->postJson(route("{$prefix}.notifikasi.baca-semua"))->assertJsonPath('data.jumlah_ditandai', 0);
    }

    public function test_status_dibaca_admin_tidak_mempengaruhi_kaprodi(): void
    {
        $this->masuk('admin', 1)->postJson(route('admin.notifikasi.baca-semua'))->assertOk();

        $this->assertNull(session(NotifikasiStaf::kunciSession('kaprodi')));
        $this->assertGreaterThan(0, NotifikasiStaf::jumlahBelumDibaca('kaprodi'));
    }

    public function test_jumlah_lonceng_mengikuti_session_dan_tidak_pernah_melempar_error(): void
    {
        $semua = count(array_filter(NotifikasiStaf::daftar('admin'), fn ($n) => $n['unread']));
        $this->assertSame($semua, NotifikasiStaf::jumlahBelumDibaca('admin'));

        $this->masuk('admin', 1)->postJson(route('admin.notifikasi.baca-semua'))->assertOk();
        $this->assertSame(0, NotifikasiStaf::jumlahBelumDibaca('admin'));

        // peran tidak dikenal = tidak melempar, cukup tanpa angka atau 0
        $this->assertNull(NotifikasiStaf::jumlahBelumDibaca('tidak-ada'));
    }
}
