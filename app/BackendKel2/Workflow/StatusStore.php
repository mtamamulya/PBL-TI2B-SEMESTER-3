<?php

namespace App\BackendKel2\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Tempat status sebuah alur disimpan. Aturan alur (WorkflowDefinition) sama untuk semua,
 * hanya cara menyimpannya yang berbeda:
 *   TimelineStore : riwayat di tabel timeline (kegiatan)
 *   ColumnStore   : kolom status di tabel modul (berita)
 */
interface StatusStore
{
    public function current(Model $subject): ?string;

    /** True bila alur sudah pernah dimulai (khusus store berbasis riwayat). */
    public function hasStarted(Model $subject): bool;

    /** Menulis perubahan. Mengembalikan baris riwayat bila store menyimpan riwayat, selain itu null. */
    public function write(Model $subject, ?string $from, string $to, ?User $actor, ?string $note): ?Model;

    public function whereStatus(Builder $query, array $codes): Builder;

    /** @return array<string,int> kode status => jumlah */
    public function countByStatus(Builder $base): array;

    /** @return Collection<int,array{status:string,at:mixed,actor:string,note:string}> */
    public function history(Model $subject): Collection;
}
