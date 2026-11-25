<?php

namespace App\BackendKel2\Workflow;

use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Dikirim setiap status berubah (termasuk status awal, from = null).
 * Dikirim setelah transaksi commit, jadi listener (notifikasi, pembuatan prestasi, dst)
 * tidak pernah bereaksi pada perubahan yang akhirnya dibatalkan.
 */
class StatusChanged implements ShouldDispatchAfterCommit
{
    /** @param  Model|null  $record  baris timeline (kegiatan) atau null untuk alur berbasis kolom (berita) */
    public function __construct(
        public readonly Model $subject,
        public readonly string $workflow,
        public readonly ?string $from,
        public readonly string $to,
        public readonly ?User $actor,
        public readonly ?string $note,
        public readonly ?Model $record = null,
    ) {}

    public function isStart(): bool
    {
        return $this->from === null;
    }

    /** Contoh: $event->became('kegiatan', 'selesai') */
    public function became(string $workflow, string $status): bool
    {
        return $this->workflow === $workflow && $this->to === $status;
    }
}
