<?php

namespace Tests\Feature;

use App\Support\ProfilProdi;
use App\Support\ProfilProdiLanding;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Navbar & footer publik membaca Profil Prodi dari API (dipalsukan), dengan cache dan cadangan. */
class ProfilProdiLandingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function profil(array $timpa = []): array
    {
        return array_merge(ProfilProdi::cadangan(), [
            'email' => 'prodi@polines.ac.id',
            'no_wa' => '6281234567890',
            'alamat' => 'Gedung Baru, Tembalang',
            'instagram' => 'https://instagram.com/trk_polines',
        ], $timpa);
    }

    private function fakeApi(array $data): void
    {
        Http::fake(['*/prodi/profil' => Http::response(['success' => true, 'message' => 'ok', 'data' => $data])]);
    }

    public function test_footer_menampilkan_kontak_dan_sosial_dari_api(): void
    {
        $this->fakeApi($this->profil());

        $this->blade('<x-footer />')
            ->assertSee('prodi@polines.ac.id')
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('Gedung Baru, Tembalang')
            ->assertSee('https://instagram.com/trk_polines', false)
            ->assertDontSee('tiktok.com')
            ->assertDontSee('sekretariat@polines.ac.id');
    }

    public function test_kolom_kosong_tidak_tampil_dan_tidak_diisi_cadangan(): void
    {
        $this->fakeApi($this->profil(['email' => null, 'no_wa' => null, 'alamat' => null, 'instagram' => null]));

        $this->blade('<x-footer />')
            ->assertDontSee('mailto:', false)
            ->assertDontSee('wa.me', false)
            ->assertDontSee('instagram.com', false)
            ->assertDontSee('sekretariat@polines.ac.id')
            ->assertDontSee('Gedung TI, Tembalang');
    }

    public function test_api_mati_landing_tetap_tampil_dengan_cadangan_aman(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->blade('<x-footer />')
            ->assertSee('sekretariat@polines.ac.id')
            ->assertSee('Gedung TI, Tembalang')
            ->assertDontSee('contoh_trk')
            ->assertDontSee('wa.me', false);
        $this->blade('<x-navbar-publik />')->assertSee('Teknologi Rekayasa Komputer');
        $this->assertTrue(Cache::has(ProfilProdiLanding::CACHE_GAGAL));
    }

    public function test_hasil_dicache_tidak_memanggil_api_dua_kali(): void
    {
        $this->fakeApi($this->profil());

        ProfilProdiLanding::ambil();
        ProfilProdiLanding::ambil();

        Http::assertSentCount(1);
    }

    public function test_api_gagal_memakai_data_terakhir_baik(): void
    {
        $this->fakeApi($this->profil(['email' => 'lama@polines.ac.id']));
        $this->assertSame('lama@polines.ac.id', ProfilProdiLanding::ambil()['email']);

        Cache::forget(ProfilProdiLanding::CACHE_KEY);
        Http::fake(fn () => throw new ConnectionException('refused'));

        $this->assertSame('lama@polines.ac.id', ProfilProdiLanding::ambil()['email']);
    }

    public function test_data_kosong_total_memakai_cadangan(): void
    {
        $this->fakeApi(array_fill_keys(ProfilProdi::KUNCI, null));

        $this->assertSame(ProfilProdi::cadangan(), ProfilProdiLanding::ambil());
    }

    public function test_respon_tidak_valid_memakai_cadangan(): void
    {
        Http::fake(['*/prodi/profil' => Http::response(['success' => true, 'message' => 'ok'])]);

        $this->assertSame(ProfilProdi::cadangan(), ProfilProdiLanding::ambil());
    }

    public function test_hasil_selalu_memuat_semua_kunci(): void
    {
        $this->fakeApi(['kode_prodi' => 'TRK', 'email' => 'a@b.id']);

        $hasil = ProfilProdiLanding::ambil();

        $this->assertEqualsCanonicalizing(ProfilProdi::KUNCI, array_keys($hasil));
        $this->assertSame('a@b.id', $hasil['email']);
    }

    public function test_logo_memakai_rantai_cadangan(): void
    {
        $this->assertStringEndsWith('images/logo_trk.jpeg', ProfilProdiLanding::logoNavbar(['logo_url' => null]));
        $this->assertSame('/storage/prodi/u.png', ProfilProdiLanding::logoFooter(['logo_url' => '/storage/prodi/u.png', 'logo_footer_url' => null]));
        $this->assertSame('/storage/prodi/f.png', ProfilProdiLanding::logoFooter(['logo_url' => '/storage/prodi/u.png', 'logo_footer_url' => '/storage/prodi/f.png']));
    }
}
