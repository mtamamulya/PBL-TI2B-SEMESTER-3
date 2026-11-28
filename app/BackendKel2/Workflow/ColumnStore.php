<?php

namespace App\BackendKel2\Workflow;

use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Status disimpan di kolom tabel modul (berita.status) dan catatan terakhir di kolom catatan.
 * Tidak menyimpan riwayat. Halaman berita memang tidak menampilkan timeline.
 */
final class ColumnStore implements StatusStore
{
    /**
     * @param  array<string,array<string,mixed>>  $onEnter  kolom tambahan yang ditulis saat masuk status tertentu;
     *                                                      nilai boleh Closure, mis. ['publish' => ['tanggal_publish' => fn () => now()]]
     */
    public function __construct(
        private readonly string $statusColumn = 'status',
        private readonly ?string $noteColumn = 'catatan_revisi',
        private readonly array $onEnter = [],
    ) {}

    public function current(Model $subject): ?string
    {
        return $subject->newQuery()->whereKey($subject->getKey())->value($this->statusColumn);
    }

    /** Kolom status sudah ada sejak baris dibuat, jadi start() cukup menetapkan status awal. */
    public function hasStarted(Model $subject): bool
    {
        return false;
    }

    public function write(Model $subject, ?string $from, string $to, ?User $actor, ?string $note): ?Model
    {
        $nilai = [$this->statusColumn => $to];

        if ($this->noteColumn !== null && filled($note)) {
            $nilai[$this->noteColumn] = trim($note);
        }

        foreach ($this->onEnter[$to] ?? [] as $kolom => $isi) {
            $nilai[$kolom] = $isi instanceof Closure ? $isi() : $isi;
        }

        $subject->newQuery()->whereKey($subject->getKey())->update($nilai);

        return null;
    }

    public function whereStatus(Builder $query, array $codes): Builder
    {
        return $query->whereIn($query->getModel()->qualifyColumn($this->statusColumn), $codes);
    }

    /** Kolom status selalu terisi, jadi semua data dianggap berstatus. */
    public function whereTracked(Builder $query, bool $tracked): Builder
    {
        return $tracked ? $query : $query->whereRaw('1 = 0');
    }

    public function countByStatus(Builder $base): array
    {
        $kolom = $base->getModel()->qualifyColumn($this->statusColumn);

        return (clone $base)->reorder()
            ->selectRaw("{$kolom} as status, count(*) as total")
            ->groupBy($kolom)
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    public function history(Model $subject): Collection
    {
        return collect();
    }
}
