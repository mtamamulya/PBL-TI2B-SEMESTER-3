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

    /** Data tanpa riwayat status yang dikenal alurnya (mis. kegiatan yang dicatat langsung). */
    public function tercatat(): bool
    {
        return $this->statusKode() === null && $this->workflowDefinition()->untrackedLabel() !== null;
    }

    public function statusLabel(string $default = 'Belum diajukan'): string
    {
        $kode = $this->statusKode();

        if ($kode !== null) {
            return $this->workflowDefinition()->label($kode);
        }

        return $this->workflowDefinition()->untrackedLabel() ?? $default;
    }

    public function bolehDiedit(): bool
    {
        $kode = $this->statusKode();

        if ($kode === null) {
            return $this->tercatat() && $this->workflowDefinition()->isUntrackedEditable();
        }

        return $this->workflowDefinition()->isEditable($kode);
    }

    public function bolehDihapus(): bool
    {
        $kode = $this->statusKode();

        if ($kode === null) {
            return $this->tercatat() && $this->workflowDefinition()->isUntrackedDeletable();
        }

        return $this->workflowDefinition()->isDeletable($kode);
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

    /** Hanya data yang pernah diajukan (punya riwayat status). Dipakai halaman Pengajuan. */
    public function scopePengajuan(Builder $query): Builder
    {
        return $this->workflowStore()->whereTracked($query, true);
    }

    /** Hanya data yang dicatat langsung (tanpa riwayat status). */
    public function scopeTercatat(Builder $query): Builder
    {
        return $this->workflowStore()->whereTracked($query, false);
    }

    /**
     * Data tercatat ATAU yang statusnya salah satu dari $codes. Dipakai halaman Kegiatan:
     * Kegiatan::where('id_user', $id)->tercatatAtauStatus('selesai').
     */
    public function scopeTercatatAtauStatus(Builder $query, string|array $codes): Builder
    {
        $store = $this->workflowStore();

        return $query->where(function (Builder $grup) use ($store, $codes) {
            $grup->where(fn (Builder $q) => $store->whereTracked($q, false))
                ->orWhere(fn (Builder $q) => $store->whereStatus($q, (array) $codes));
        });
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
