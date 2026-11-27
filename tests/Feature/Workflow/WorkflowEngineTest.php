<?php

namespace Tests\Feature\Workflow;

use App\BackendKel2\Workflow\HasWorkflow;
use App\BackendKel2\Workflow\InvalidTransition;
use App\BackendKel2\Workflow\StatusChanged;
use App\BackendKel2\Workflow\Timeline;
use App\BackendKel2\Workflow\TransitionForbidden;
use App\BackendKel2\Workflow\Workflowable;
use App\BackendKel2\Workflow\WorkflowEngine;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

/**
 * PERHATIAN: RefreshDatabase menjalankan migrate:fresh pada database tes.
 * Pastikan phpunit.xml / .env.testing memakai database TERPISAH, bukan database pengembangan.
 *
 * Tes memakai model tipis pada tabel asli agar foreign key tetap aktif tanpa mengubah
 * status transaksional koneksi MySQL.
 */
class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowEngine $engine;

    private User $owner;

    private User $staff;

    private User $stranger;

    private int $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->stranger = User::factory()->create();

        $jenis = DB::table('jenis_kegiatan')->insertGetId(['nama_jenis_kegiatan' => 'Kegiatan Dosen']);
        $this->category = DB::table('kategori')->insertGetId([
            'id_jenis_kegiatan' => $jenis,
            'nama_kategori' => 'Workflow Test',
        ]);

        $staffId = $this->staff->getKey();
        Gate::define('review-workflow', fn ($user) => $user->getKey() === $staffId);

        $this->engine = app(WorkflowEngine::class);
    }

    private function kegiatan(?User $pemilik = null): DummyKegiatan
    {
        return DummyKegiatan::create([
            'id_kategori' => $this->category,
            'id_user' => ($pemilik ?? $this->owner)->getKey(),
            'nama_kegiatan' => 'Workflow test activity',
        ]);
    }

    private function berita(): DummyBerita
    {
        return DummyBerita::create([
            'id_user' => $this->owner->getKey(),
            'judul_berita' => 'Workflow test news',
            'isi_berita' => 'Workflow test content',
        ]);
    }

    // ---------------------------------------------------------------- kegiatan (tabel timeline)

    public function test_start_mencatat_status_awal(): void
    {
        $k = $this->kegiatan();

        $this->engine->start($k, $this->owner);

        $this->assertSame('diajukan', $k->fresh()->statusKode());
        $this->assertSame('Diajukan', $k->fresh()->statusLabel());
        $this->assertSame('diajukan', $k->fresh()->statusTerkini->status);   // dipakai Blade dashboard
    }

    public function test_start_dua_kali_ditolak(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);

        $this->expectException(LogicException::class);
        $this->engine->start($k, $this->owner);
    }

    public function test_transisi_sah_menambah_baris_timeline_dan_mengirim_event(): void
    {
        Event::fake([StatusChanged::class]);
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);

        $this->engine->transition($k, 'direview', $this->staff);

        $this->assertSame('direview', $k->fresh()->statusKode());
        $this->assertSame(2, Timeline::count());
        Event::assertDispatched(StatusChanged::class, fn ($e) => $e->from === 'diajukan' && $e->to === 'direview');
    }

    public function test_pelaku_dan_catatan_tersimpan_di_timeline(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);
        $this->engine->transition($k, 'direview', $this->staff);
        $this->engine->transition($k, 'direvisi', $this->staff, 'Lengkapi lampiran.');

        $baris = Timeline::orderByDesc('id_timeline')->first();

        $this->assertSame('direvisi', $baris->status);
        $this->assertSame('Lengkapi lampiran.', $baris->catatan_revisi);
        $this->assertSame($this->staff->getKey(), $baris->id_user);
    }

    public function test_transisi_tidak_sah_ditolak_dan_tidak_meninggalkan_baris(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);

        try {
            $this->engine->transition($k, 'selesai', $this->staff);
            $this->fail('InvalidTransition diharapkan');
        } catch (InvalidTransition) {
            // diharapkan
        }

        $this->assertSame(1, Timeline::count());
    }

    public function test_pelaku_yang_salah_ditolak(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);

        $this->expectException(TransitionForbidden::class);
        $this->engine->transition($k, 'direview', $this->owner);   // pemilik tidak boleh mereview
    }

    public function test_catatan_wajib_untuk_revisi(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);
        $this->engine->transition($k, 'direview', $this->staff);

        $this->expectException(ValidationException::class);
        $this->engine->transition($k, 'direvisi', $this->staff, '   ');
    }

    public function test_pemilik_mengajukan_ulang_dan_orang_lain_tidak_bisa(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);
        $this->engine->transition($k, 'direview', $this->staff);
        $this->engine->transition($k, 'direvisi', $this->staff, 'Lengkapi lampiran.');

        try {
            $this->engine->transition($k, 'diajukan', $this->stranger);
            $this->fail('TransitionForbidden diharapkan');
        } catch (TransitionForbidden) {
            // diharapkan
        }

        $this->engine->transition($k, 'diajukan', $this->owner);
        $this->assertSame('diajukan', $k->fresh()->statusKode());
    }

    public function test_status_akhir_tidak_bisa_diubah(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);
        $this->engine->transition($k, 'direview', $this->staff);
        $this->engine->transition($k, 'ditolak', $this->staff, 'Di luar periode.');

        $this->expectException(InvalidTransition::class);
        $this->engine->transition($k, 'diajukan', $this->owner);
    }

    public function test_proses_sistem_hanya_untuk_transisi_yang_mengizinkan(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);
        $this->engine->transition($k, 'direview', $this->staff);
        $this->engine->transition($k, 'diterima', $this->staff);

        $this->engine->transition($k, 'selesai', null);               // sistem boleh

        $this->assertSame('selesai', $k->fresh()->statusKode());
    }

    public function test_sistem_tidak_boleh_menyetujui(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);
        $this->engine->transition($k, 'direview', $this->staff);

        $this->expectException(TransitionForbidden::class);
        $this->engine->transition($k, 'diterima', null);
    }

    public function test_aksi_yang_tersedia_untuk_tiap_pelaku(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);

        $this->assertSame(['direview'], array_column($this->engine->availableTransitions($k, $this->staff), 'to'));
        $this->assertSame([], $this->engine->availableTransitions($k, $this->owner));
    }

    public function test_timeline_sesuai_format_blade(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);
        $this->engine->transition($k, 'direview', $this->staff);
        $this->engine->transition($k, 'direvisi', $this->staff, 'Lengkapi lampiran.');

        $steps = $k->timeline();

        $this->assertSame(['Diajukan', 'Direview', 'Perlu revisi'], $steps->pluck('title')->all());
        $this->assertSame($this->staff->nama_tampil ?? $this->staff->name ?? $this->staff->email, $steps->last()['actor']);
        $this->assertSame('Lengkapi lampiran.', $steps->last()['note']);
        $this->assertTrue($k->fresh()->bolehDiedit());
    }

    public function test_filter_dan_hitungan_per_status_kegiatan(): void
    {
        [$a, $b, $c] = [$this->kegiatan(), $this->kegiatan(), $this->kegiatan()];
        foreach ([$a, $b, $c] as $k) {
            $this->engine->start($k, $this->owner);
        }
        $this->engine->transition($b, 'direview', $this->staff);
        $this->engine->transition($c, 'direview', $this->staff);
        $this->engine->transition($c, 'ditolak', $this->staff, 'Di luar periode.');

        $this->assertSame([$a->getKey()], DummyKegiatan::whereStatus('diajukan')->pluck('id_kegiatan')->all());
        $this->assertSame(2, DummyKegiatan::whereStatus(['direview', 'ditolak'])->count());
        $this->assertSame(
            ['diajukan' => 1, 'direview' => 1, 'ditolak' => 1],
            $this->engine->countByStatus(DummyKegiatan::query())
        );
        $this->assertSame(1, $this->engine->countByLabel(DummyKegiatan::query())['Ditolak']);
    }

    public function test_timeline_tidak_bisa_diubah_atau_dihapus(): void
    {
        $k = $this->kegiatan();
        $this->engine->start($k, $this->owner);
        $baris = Timeline::first();

        try {
            $baris->update(['catatan_revisi' => 'diubah']);
            $this->fail('LogicException diharapkan saat update');
        } catch (LogicException) {
            // diharapkan
        }

        $this->expectException(LogicException::class);
        $baris->delete();
    }

    // ---------------------------------------------------------------- kegiatan dicatat langsung (tanpa timeline)

    public function test_kegiatan_dicatat_langsung_berlabel_tercatat_tanpa_timeline(): void
    {
        $k = $this->kegiatan();                       // tanpa start(): tidak ada persetujuan

        $this->assertNull($k->statusKode());
        $this->assertSame('Tercatat', $k->statusLabel());
        $this->assertTrue($k->tercatat());
        $this->assertTrue($k->bolehDiedit());
        $this->assertTrue($k->bolehDihapus());
        $this->assertTrue($k->timeline()->isEmpty());
        $this->assertSame(0, Timeline::count());
    }

    public function test_kegiatan_tercatat_tidak_bisa_diproses_workflow(): void
    {
        $k = $this->kegiatan();

        $this->expectException(InvalidTransition::class);
        $this->engine->transition($k, 'direview', $this->staff);
    }

    public function test_daftar_kegiatan_memuat_tercatat_dan_selesai_milik_pemilik(): void
    {
        $catat = $this->kegiatan();
        $diproses = $this->kegiatan();
        $selesai = $this->kegiatan();
        $milikLain = $this->kegiatan($this->stranger);   // tercatat, tetapi bukan milik owner

        $this->engine->start($diproses, $this->owner);
        $this->engine->start($selesai, $this->owner);
        $this->engine->transition($selesai, 'direview', $this->staff);
        $this->engine->transition($selesai, 'diterima', $this->staff);
        $this->engine->transition($selesai, 'selesai', null);

        $owner = fn () => DummyKegiatan::where('id_user', $this->owner->getKey());

        $daftar = $owner()->tercatatAtauStatus('selesai')->pluck('id_kegiatan')->sort()->values()->all();
        $this->assertSame([$catat->getKey(), $selesai->getKey()], $daftar);
        $this->assertNotContains($milikLain->getKey(), $daftar);

        $this->assertSame([$catat->getKey()], $owner()->tercatat()->pluck('id_kegiatan')->all());
        $this->assertSame(
            [$diproses->getKey(), $selesai->getKey()],
            $owner()->pengajuan()->pluck('id_kegiatan')->sort()->values()->all()
        );

        // Kegiatan tercatat bukan pengajuan, jadi tidak ikut hitungan status.
        $this->assertSame(['diajukan' => 1, 'selesai' => 1], $this->engine->countByStatus($owner()));
    }

    // ---------------------------------------------------------------- berita (kolom status)

    public function test_berita_start_di_draft_lalu_diajukan_oleh_pemilik(): void
    {
        $b = $this->berita();

        $this->assertNull($this->engine->start($b, $this->owner));       // tanpa baris riwayat
        $this->assertSame('draft', $b->fresh()->statusKode());

        $this->engine->transition($b, 'diajukan', $this->owner);
        $this->assertSame('diajukan', $b->fresh()->status);
    }

    public function test_berita_langsung_diajukan_saat_start(): void
    {
        $b = $this->berita();

        $this->engine->start($b, $this->owner, 'diajukan');

        $this->assertSame('diajukan', $b->fresh()->status);
    }

    public function test_berita_publish_mengisi_tanggal_publish(): void
    {
        $b = $this->berita();
        $this->engine->start($b, $this->owner, 'diajukan');

        $this->engine->transition($b, 'publish', $this->staff);

        $b = $b->fresh();
        $this->assertSame('publish', $b->status);
        $this->assertSame('Dipublikasikan', $b->statusLabel());
        $this->assertNotNull($b->tanggal_publish);
    }

    public function test_berita_revisi_menyimpan_catatan_di_kolom(): void
    {
        $b = $this->berita();
        $this->engine->start($b, $this->owner, 'diajukan');

        $this->engine->transition($b, 'direvisi', $this->staff, 'Perbaiki judul.');

        $b = $b->fresh();
        $this->assertSame('direvisi', $b->status);
        $this->assertSame('Perbaiki judul.', $b->catatan_revisi);
        $this->assertTrue($b->bolehDiedit());
    }

    public function test_berita_staff_tidak_bisa_langsung_publish_draft(): void
    {
        $b = $this->berita();
        $this->engine->start($b, $this->owner);

        $this->expectException(InvalidTransition::class);
        $this->engine->transition($b, 'publish', $this->staff);
    }

    public function test_berita_filter_dan_hitungan(): void
    {
        $draft = $this->berita();
        $diajukan = $this->berita();
        $this->engine->start($draft, $this->owner);
        $this->engine->start($diajukan, $this->owner, 'diajukan');

        $this->assertSame([$diajukan->getKey()], DummyBerita::whereStatus('diajukan')->pluck('id_berita')->all());
        $this->assertSame(
            ['draft' => 1, 'diajukan' => 1],
            $this->engine->countByStatus(DummyBerita::query())
        );
        $this->assertSame(1, $this->engine->countByLabel(DummyBerita::query())['Draft']);
    }
}

class DummyKegiatan extends Model implements Workflowable
{
    use HasWorkflow;

    protected $table = 'kegiatan';

    protected $primaryKey = 'id_kegiatan';

    protected $guarded = [];

    public static function workflowKey(): string
    {
        return 'kegiatan';
    }

    public function workflowOwnerId(): int|string|null
    {
        return $this->id_user;
    }
}

class DummyBerita extends Model implements Workflowable
{
    use HasWorkflow;

    protected $table = 'berita';

    protected $primaryKey = 'id_berita';

    protected $guarded = [];

    public static function workflowKey(): string
    {
        return 'berita';
    }

    public function workflowOwnerId(): int|string|null
    {
        return $this->id_user;
    }
}
