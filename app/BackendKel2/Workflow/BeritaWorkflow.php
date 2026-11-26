<?php

namespace App\BackendKel2\Workflow;

/**
 * Alur pengajuan berita. Status disimpan di kolom berita.status (ColumnStore).
 *
 *   draft -> diajukan -> publish
 *               |-> direvisi -> diajukan
 *               |-> ditolak (status akhir)
 *
 * Kode 'publish' mengikuti ERD, labelnya tetap "Dipublikasikan" seperti di UI.
 * Enum ERD (publish, draft) perlu diperluas dengan diajukan, direvisi, ditolak.
 * "Ajukan Berita" boleh langsung memulai alur di status diajukan.
 */
final class BeritaWorkflow
{
    public const KEY = 'berita';

    public static function definition(): WorkflowDefinition
    {
        return new WorkflowDefinition(
            key: self::KEY,
            labels: [
                'draft' => 'Draft',
                'diajukan' => 'Diajukan',
                'direvisi' => 'Direvisi',
                'publish' => 'Dipublikasikan',
                'ditolak' => 'Ditolak',
            ],
            startable: ['draft', 'diajukan'],
            transitions: [
                ['from' => 'draft', 'to' => 'diajukan', 'by' => [Actor::OWNER]],
                ['from' => 'diajukan', 'to' => 'publish', 'by' => [Actor::STAFF]],
                ['from' => 'diajukan', 'to' => 'direvisi', 'by' => [Actor::STAFF], 'note' => true],
                ['from' => 'diajukan', 'to' => 'ditolak', 'by' => [Actor::STAFF], 'note' => true],
                ['from' => 'direvisi', 'to' => 'diajukan', 'by' => [Actor::OWNER]],
            ],
            // Sesuai Blade: tombol edit hanya untuk Draft dan Direvisi.
            editable: ['draft', 'direvisi'],
            deletable: ['draft'],
        );
    }
}
