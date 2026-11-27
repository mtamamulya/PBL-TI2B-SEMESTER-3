<?php

namespace App\BackendKel2\Workflow;

/**
 * Alur pengajuan kegiatan (juga dipakai pengajuan surat bila disepakati).
 *
 *   diajukan -> direview -> diterima -> selesai
 *                  |-> direvisi -> diajukan (diajukan ulang oleh pemilik)
 *                  |-> ditolak (status akhir)
 *
 * Label mengikuti UI saat ini: direvisi = "Perlu revisi", diterima = "Disetujui".
 *
 * Kegiatan yang dicatat langsung lewat "Tambah Kegiatan" tidak melewati persetujuan dan tidak punya
 * baris timeline sama sekali. Di sini disebut "Tercatat". Mesin tidak dipanggil untuk data seperti itu.
 */
final class KegiatanWorkflow
{
    public const KEY = 'kegiatan';

    public static function definition(): WorkflowDefinition
    {
        return new WorkflowDefinition(
            key: self::KEY,
            labels: [
                'diajukan' => 'Diajukan',
                'direview' => 'Direview',
                'direvisi' => 'Perlu revisi',
                'diterima' => 'Disetujui',
                'ditolak' => 'Ditolak',
                'selesai' => 'Selesai',
            ],
            startable: ['diajukan'],
            transitions: [
                ['from' => 'diajukan', 'to' => 'direview', 'by' => [Actor::STAFF]],
                ['from' => 'direview', 'to' => 'diterima', 'by' => [Actor::STAFF]],
                ['from' => 'direview', 'to' => 'direvisi', 'by' => [Actor::STAFF], 'note' => true],
                ['from' => 'direview', 'to' => 'ditolak', 'by' => [Actor::STAFF], 'note' => true],
                ['from' => 'direvisi', 'to' => 'diajukan', 'by' => [Actor::OWNER]],
                ['from' => 'diterima', 'to' => 'selesai', 'by' => [Actor::STAFF, Actor::SYSTEM]],
            ],
            // Aturan awal, perlu disetujui tim: edit dan hapus hanya sebelum ditinjau atau saat perlu revisi.
            editable: ['diajukan', 'direvisi'],
            deletable: ['diajukan', 'direvisi'],
            // Kegiatan tercatat tidak butuh persetujuan, jadi pemilik bebas mengubah dan menghapusnya.
            untrackedLabel: 'Tercatat',
            untrackedEditable: true,
            untrackedDeletable: true,
        );
    }
}
