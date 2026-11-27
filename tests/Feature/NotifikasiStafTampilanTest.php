<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\NotifikasiStaf;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotifikasiStafTampilanTest extends TestCase
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
        return [
            'admin' => ['admin', 1],
            'kaprodi' => ['kaprodi', 2],
        ];
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

    #[DataProvider('peran')]
    public function test_halaman_notifikasi_dapat_dibuka_dan_menampilkan_konten_dummy(string $prefix, int $idRole): void
    {
        $pertama = NotifikasiStaf::daftar($prefix)[0];

        $this->masuk($prefix, $idRole)
            ->get(route("{$prefix}.notifikasi"))
            ->assertOk()
            ->assertSee('Notifikasi')
            ->assertSee($pertama['title'])
            ->assertSee('Mode dummy');
    }

    public function test_notifikasi_khusus_peran_tidak_tampil_di_peran_lain(): void
    {
        $this->masuk('admin', 1)
            ->get(route('admin.notifikasi'))
            ->assertOk()
            ->assertSee('Pengajuan baru masuk')
            ->assertDontSee('Menunggu tanda tangan Anda');

        $this->masuk('kaprodi', 2)
            ->get(route('kaprodi.notifikasi'))
            ->assertOk()
            ->assertSee('Menunggu tanda tangan Anda')
            ->assertDontSee('Pengajuan baru masuk');
    }

    #[DataProvider('peran')]
    public function test_tiga_judul_kelompok_waktu_tampil(string $prefix, int $idRole): void
    {
        $this->masuk($prefix, $idRole)
            ->get(route("{$prefix}.notifikasi"))
            ->assertOk()
            ->assertSee('Hari ini')
            ->assertSee('Kemarin')
            ->assertSee('Lebih lama');
    }

    #[DataProvider('peran')]
    public function test_setelah_baca_semua_lonceng_tidak_menampilkan_lencana_angka(string $prefix, int $idRole): void
    {
        $jumlahAwal = NotifikasiStaf::jumlahBelumDibaca($prefix);
        $this->assertGreaterThan(0, $jumlahAwal);

        $akun = $this->masuk($prefix, $idRole);

        $akun->postJson(route("{$prefix}.notifikasi.baca-semua"))
            ->assertOk();

        $akun->get(route("{$prefix}.notifikasi"))
            ->assertOk()
            ->assertSee('Notifikasi')
            ->assertDontSee("Notifikasi, {$jumlahAwal} belum dibaca")
            ->assertSee('data-notif-badge', false)
            ->assertSee('style="display: none;"', false);
    }

    #[DataProvider('peran')]
    public function test_dashboard_memuat_tautan_ke_halaman_notifikasi(string $prefix, int $idRole): void
    {
        $this->masuk($prefix, $idRole)
            ->get(route("{$prefix}.dashboard"))
            ->assertOk()
            ->assertSee(route("{$prefix}.notifikasi"), false);
    }

    public function test_komponen_lonceng_tetap_tampil_tanpa_error_untuk_peran_tidak_dikenal(): void
    {
        $this->assertNull(NotifikasiStaf::jumlahBelumDibaca('tidak-ada'));

        $this->blade('<x-notifikasi-lonceng prefix="tidak-ada" />')
            ->assertSee('Notifikasi')
            ->assertDontSee('data-notif-badge', false);

        $this->blade('<x-notifikasi-lonceng prefix="admin" />')
            ->assertSee('Notifikasi')
            ->assertSee('data-notif-badge', false);
    }
}
