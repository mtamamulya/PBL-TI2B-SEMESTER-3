<?php

namespace Tests\Feature;

use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimelineApiTest extends TestCase
{
    use RefreshDatabase;

    private function user(int $roleId, string $email): User
    {
        DB::table('roles')->insertOrIgnore([
            'id_role' => $roleId,
            'nama_role' => match ($roleId) {
                1 => 'admin',
                2 => 'kaprodi',
                3 => 'dosen',
                default => 'mahasiswa',
            },
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::create([
            'id_role' => $roleId,
            'email' => $email,
            'password' => 'password',
            'status' => true,
        ]);
    }

    private function kategoriId(): int
    {
        // Kolom disesuaikan dengan migration kategori & jenis_kegiatan bila berbeda.
        DB::table('jenis_kegiatan')->insertOrIgnore([
            'id_jenis_kegiatan' => 1,
            'nama_jenis_kegiatan' => 'Kegiatan Mahasiswa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('kategori')->insertOrIgnore([
            'id_kategori' => 1,
            'id_jenis_kegiatan' => 1,
            'nama_kategori' => 'Lomba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return 1;
    }

    private function kegiatan(User $pemilik, string $nama = 'Lomba Uji'): Kegiatan
    {
        return Kegiatan::create([
            'id_kategori' => $this->kategoriId(),
            'id_user' => $pemilik->getKey(),
            'nama_kegiatan' => $nama,
        ]);
    }

    private function timeline(Kegiatan $kegiatan, string $status, string $waktu, ?string $catatan = null): Timeline
    {
        return Timeline::create([
            'id_kegiatan' => $kegiatan->getKey(),
            'status' => $status,
            'tanggal_status' => $waktu,
            'catatan_revisi' => $catatan,
        ]);
    }

    public function test_timeline_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/mahasiswa/timeline')->assertUnauthorized();
        $this->getJson('/api/v1/dosen/timeline')->assertUnauthorized();
    }

    public function test_each_timeline_endpoint_rejects_the_other_role(): void
    {
        $mahasiswa = $this->user(4, 'mahasiswa@test.local');
        $dosen = $this->user(3, 'dosen@test.local');

        Sanctum::actingAs($mahasiswa);
        $this->getJson('/api/v1/dosen/timeline')->assertForbidden();

        Sanctum::actingAs($dosen);
        $this->getJson('/api/v1/mahasiswa/timeline')->assertForbidden();
    }

    public function test_mahasiswa_only_sees_own_kegiatan_with_ordered_timeline(): void
    {
        $saya = $this->user(4, 'saya@test.local');
        $lain = $this->user(4, 'lain@test.local');

        $milikSaya = $this->kegiatan($saya, 'Kegiatan Saya');
        // Sengaja dibuat tidak berurutan untuk menguji pengurutan.
        $this->timeline($milikSaya, 'diterima', '2026-11-10 10:00:00');
        $this->timeline($milikSaya, 'diajukan', '2026-11-10 08:00:00');
        $this->timeline($milikSaya, 'direview', '2026-11-10 09:00:00');

        $milikLain = $this->kegiatan($lain, 'Kegiatan Orang Lain');
        $this->timeline($milikLain, 'diajukan', '2026-11-11 08:00:00');

        Sanctum::actingAs($saya);

        $this->getJson('/api/v1/mahasiswa/timeline')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_kegiatan', $milikSaya->id_kegiatan)
            ->assertJsonPath('data.0.status', 'diterima')
            ->assertJsonPath('data.0.label_status', 'Disetujui')
            ->assertJsonCount(3, 'data.0.timeline')
            ->assertJsonPath('data.0.timeline.0.status', 'diajukan')
            ->assertJsonPath('data.0.timeline.1.status', 'direview')
            ->assertJsonPath('data.0.timeline.2.status', 'diterima');
    }

    public function test_dosen_only_sees_own_kegiatan(): void
    {
        $dosen = $this->user(3, 'dosen@test.local');
        $dosenLain = $this->user(3, 'dosen-lain@test.local');

        $milikDosen = $this->kegiatan($dosen, 'Penelitian Dosen');
        $this->timeline($milikDosen, 'diajukan', '2026-11-10 08:00:00');
        $this->timeline($milikDosen, 'direvisi', '2026-11-10 09:00:00', 'Lengkapi lampiran.');

        $this->timeline($this->kegiatan($dosenLain, 'Penelitian Dosen Lain'), 'diajukan', '2026-11-10 08:00:00');

        Sanctum::actingAs($dosen);

        $this->getJson('/api/v1/dosen/timeline')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id_kegiatan', $milikDosen->id_kegiatan)
            ->assertJsonPath('data.0.status', 'direvisi')
            ->assertJsonPath('data.0.timeline.1.catatan_revisi', 'Lengkapi lampiran.');
    }

    public function test_status_filter_only_returns_kegiatan_with_that_latest_status(): void
    {
        $saya = $this->user(4, 'saya@test.local');

        $a = $this->kegiatan($saya, 'Kegiatan A');
        $this->timeline($a, 'diajukan', '2026-11-10 08:00:00');

        $b = $this->kegiatan($saya, 'Kegiatan B');
        $this->timeline($b, 'diajukan', '2026-11-10 08:00:00');
        $this->timeline($b, 'direvisi', '2026-11-10 09:00:00');

        Sanctum::actingAs($saya);

        $this->getJson('/api/v1/mahasiswa/timeline?status=direvisi')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id_kegiatan', $b->id_kegiatan);
    }

    public function test_id_kegiatan_filter_cannot_reach_other_users_kegiatan(): void
    {
        $saya = $this->user(4, 'saya@test.local');
        $lain = $this->user(4, 'lain@test.local');

        $milikLain = $this->kegiatan($lain);
        $this->timeline($milikLain, 'diajukan', '2026-11-10 08:00:00');

        Sanctum::actingAs($saya);

        $this->getJson('/api/v1/mahasiswa/timeline?id_kegiatan='.$milikLain->id_kegiatan)
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        Sanctum::actingAs($this->user(4, 'saya@test.local'));

        $this->getJson('/api/v1/mahasiswa/timeline?status=tidak-ada')->assertUnprocessable();
    }
}
