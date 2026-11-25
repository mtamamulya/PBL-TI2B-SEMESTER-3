<?php

namespace App\BackendKel2\Workflow;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Dipasang di model yang mengimplementasikan Workflowable.
 * Cara status dibaca mengikuti store alurnya (timeline untuk kegiatan, kolom untuk berita).
 */
trait HasWorkflow
{
    /**
     * Khusus alur berbasis tabel timeline (kegiatan). Dipakai Blade sebagai
     * $item->statusTerkini?->status dan bisa di-eager-load: ->with('statusTerkini').
     */
    public function statusTerkini(): HasOne
    {
        return $this->hasOne(Timeline::class, 'id_kegiatan', $this->getKeyName())
            ->orderByDesc('id_timeline');
    }

    /** Kode status saat ini, atau null kalau alur belum dimulai. */
    public function statusKode(): ?string
    {
        if ($this->relationLoaded('statusTerkini')) {
            return $this->getRelation('statusTerkini')?->status;
        }

        return $this->workflowStore()->current($this);
    }

    public function statusLabel(string $default = 'Belum diajukan'): string
    {
        $kode = $this->statusKode();

        return $kode === null ? $default : $this->workflowDefinition()->label($kode);
    }

    public function bolehDiedit(): bool
    {
        $kode = $this->statusKode();

        return $kode !== null && $this->workflowDefinition()->isEditable($kode);
    }

    public function bolehDihapus(): bool
    {
        $kode = $this->statusKode();

        return $kode !== null && $this->workflowDefinition()->isDeletable($kode);
    }

    /**
     * Langkah riwayat dalam format yang dibaca Blade detail pengajuan:
     * [['title', 'at', 'actor', 'note'], ...]. Kosong untuk alur tanpa riwayat (berita).
     */
    public function timeline(): Collection
    {
        $definition = $this->workflowDefinition();

        return $this->workflowStore()->history($this)->map(fn (array $h) => [
            'title' => $definition->label($h['status']),
            'at' => $h['at'],
            'actor' => $h['actor'],
            'note' => $h['note'],
        ]);
    }

    /** Filter berdasarkan status terkini. Menerima satu kode atau daftar kode. */
    public function scopeWhereStatus(Builder $query, string|array $codes): Builder
    {
        return $this->workflowStore()->whereStatus($query, (array) $codes);
    }

    protected function workflowDefinition(): WorkflowDefinition
    {
        return app(WorkflowRegistry::class)->for(static::workflowKey());
    }

    protected function workflowStore(): StatusStore
    {
        return app(WorkflowRegistry::class)->store(static::workflowKey());
    }
}
