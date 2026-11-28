<?php

namespace Tests\Unit\Workflow;

use App\BackendKel2\Workflow\Actor;
use App\BackendKel2\Workflow\BeritaWorkflow;
use App\BackendKel2\Workflow\KegiatanWorkflow;
use App\BackendKel2\Workflow\WorkflowDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WorkflowDefinitionTest extends TestCase
{
    public function test_kegiatan_transisi_yang_sah(): void
    {
        $d = KegiatanWorkflow::definition();

        $this->assertTrue($d->canTransition('diajukan', 'direview'));
        $this->assertTrue($d->canTransition('direview', 'diterima'));
        $this->assertTrue($d->canTransition('direview', 'direvisi'));
        $this->assertTrue($d->canTransition('direview', 'ditolak'));
        $this->assertTrue($d->canTransition('direvisi', 'diajukan'));
        $this->assertTrue($d->canTransition('diterima', 'selesai'));
    }

    public function test_kegiatan_transisi_yang_tidak_sah(): void
    {
        $d = KegiatanWorkflow::definition();

        $this->assertFalse($d->canTransition('diajukan', 'diterima'));   // harus lewat direview
        $this->assertFalse($d->canTransition('diajukan', 'selesai'));
        $this->assertFalse($d->canTransition('ditolak', 'diajukan'));    // ditolak = akhir
        $this->assertFalse($d->canTransition('selesai', 'direview'));
        $this->assertFalse($d->canTransition('direview', 'diajukan'));
    }

    public function test_status_akhir(): void
    {
        $d = KegiatanWorkflow::definition();

        $this->assertTrue($d->isFinal('ditolak'));
        $this->assertTrue($d->isFinal('selesai'));
        $this->assertFalse($d->isFinal('direview'));
    }

    public function test_catatan_wajib_untuk_revisi_dan_penolakan(): void
    {
        $d = KegiatanWorkflow::definition();

        $this->assertTrue($d->requiresNote('direview', 'direvisi'));
        $this->assertTrue($d->requiresNote('direview', 'ditolak'));
        $this->assertFalse($d->requiresNote('direview', 'diterima'));
        $this->assertFalse($d->requiresNote('diajukan', 'direview'));
    }

    public function test_pelaku_transisi(): void
    {
        $d = KegiatanWorkflow::definition();

        $this->assertSame([Actor::STAFF], $d->rule('diajukan', 'direview')['by']);
        $this->assertSame([Actor::OWNER], $d->rule('direvisi', 'diajukan')['by']);
        $this->assertSame([Actor::STAFF, Actor::SYSTEM], $d->rule('diterima', 'selesai')['by']);
    }

    public function test_label_mengikuti_ui_dan_kode_tetap(): void
    {
        $d = KegiatanWorkflow::definition();

        $this->assertSame('Perlu revisi', $d->label('direvisi'));
        $this->assertSame('Disetujui', $d->label('diterima'));
        $this->assertSame('direvisi', $d->codeForLabel('perlu revisi'));
        $this->assertSame('diterima', $d->codeForLabel('Disetujui'));
        $this->assertNull($d->codeForLabel('Tidak ada'));
    }

    public function test_aturan_edit_dan_hapus(): void
    {
        $k = KegiatanWorkflow::definition();
        $this->assertTrue($k->isEditable('diajukan'));
        $this->assertTrue($k->isEditable('direvisi'));
        $this->assertFalse($k->isEditable('direview'));
        $this->assertFalse($k->isDeletable('selesai'));

        $b = BeritaWorkflow::definition();
        $this->assertTrue($b->isEditable('draft'));
        $this->assertFalse($b->isEditable('diajukan'));
    }

    public function test_status_awal(): void
    {
        $k = KegiatanWorkflow::definition();
        $this->assertSame('diajukan', $k->defaultStart());
        $this->assertFalse($k->canStartAt('selesai'));

        $b = BeritaWorkflow::definition();
        $this->assertSame('draft', $b->defaultStart());
        $this->assertTrue($b->canStartAt('diajukan'));
        $this->assertFalse($b->canStartAt('publish'));
    }

    public function test_berita_kode_publish_berlabel_dipublikasikan(): void
    {
        $b = BeritaWorkflow::definition();

        $this->assertSame('Dipublikasikan', $b->label('publish'));
        $this->assertSame('publish', $b->codeForLabel('Dipublikasikan'));
    }

    public function test_kegiatan_tanpa_riwayat_berstatus_tercatat(): void
    {
        $k = KegiatanWorkflow::definition();

        $this->assertSame('Tercatat', $k->untrackedLabel());
        $this->assertTrue($k->isUntrackedEditable());
        $this->assertTrue($k->isUntrackedDeletable());

        $b = BeritaWorkflow::definition();
        $this->assertNull($b->untrackedLabel());
        $this->assertFalse($b->isUntrackedEditable());
    }

    public function test_berita_alur_publikasi(): void
    {
        $b = BeritaWorkflow::definition();

        $this->assertTrue($b->canTransition('draft', 'diajukan'));
        $this->assertTrue($b->canTransition('diajukan', 'publish'));
        $this->assertFalse($b->canTransition('draft', 'publish'));
        $this->assertTrue($b->isFinal('publish'));
        $this->assertTrue($b->isFinal('ditolak'));
    }

    public function test_definisi_dengan_status_tidak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WorkflowDefinition(
            key: 'rusak',
            labels: ['a' => 'A'],
            startable: ['a'],
            transitions: [['from' => 'a', 'to' => 'b', 'by' => [Actor::OWNER]]],
        );
    }

    public function test_definisi_dengan_pelaku_tidak_valid_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WorkflowDefinition(
            key: 'rusak',
            labels: ['a' => 'A', 'b' => 'B'],
            startable: ['a'],
            transitions: [['from' => 'a', 'to' => 'b', 'by' => ['dewa']]],
        );
    }
}
