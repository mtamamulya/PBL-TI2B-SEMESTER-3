<?php

namespace App\BackendKel2\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Status = baris terakhir di tabel timeline (id_timeline terbesar) untuk sebuah kegiatan. */
final class TimelineStore implements StatusStore
{
    public function current(Model $subject): ?string
    {
        return Timeline::where('id_kegiatan', $subject->getKey())
            ->orderByDesc('id_timeline')
            ->value('status');
    }

    public function hasStarted(Model $subject): bool
    {
        return $this->current($subject) !== null;
    }

    public function write(Model $subject, ?string $from, string $to, ?User $actor, ?string $note): ?Model
    {
        return Timeline::create([
            'id_kegiatan' => $subject->getKey(),
            'status' => $to,
            'tanggal_status' => now(),
            'catatan_revisi' => filled($note) ? trim($note) : null,
            'id_user' => $actor?->getKey(),
        ]);
    }

    public function whereStatus(Builder $query, array $codes): Builder
    {
        $kunci = $query->getModel()->getQualifiedKeyName();

        return $query->whereExists(function ($q) use ($codes, $kunci) {
            $q->selectRaw('1')
                ->from('timeline as t')
                ->whereColumn('t.id_kegiatan', $kunci)
                ->whereIn('t.status', $codes)
                ->whereRaw(
                    't.id_timeline = (select max(t2.id_timeline) from timeline as t2 '
                    .'where t2.id_kegiatan = t.id_kegiatan)'
                );
        });
    }

    public function whereTracked(Builder $query, bool $tracked): Builder
    {
        $kunci = $query->getModel()->getQualifiedKeyName();

        $adaTimeline = fn ($q) => $q->selectRaw('1')
            ->from('timeline as t')
            ->whereColumn('t.id_kegiatan', $kunci);

        return $tracked ? $query->whereExists($adaTimeline) : $query->whereNotExists($adaTimeline);
    }

    public function countByStatus(Builder $base): array
    {
        $ids = (clone $base)->select($base->getModel()->getQualifiedKeyName());

        $terakhir = DB::table('timeline')
            ->selectRaw('max(id_timeline) as last_id')
            ->whereIn('id_kegiatan', $ids)
            ->groupBy('id_kegiatan');

        return DB::table('timeline as t')
            ->joinSub($terakhir, 'l', 't.id_timeline', '=', 'l.last_id')
            ->selectRaw('t.status as status, count(*) as total')
            ->groupBy('t.status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    public function history(Model $subject): Collection
    {
        return Timeline::with('actor')
            ->where('id_kegiatan', $subject->getKey())
            ->orderBy('id_timeline')
            ->get()
            ->map(fn (Timeline $t) => [
                'status' => $t->status,
                'at' => $t->tanggal_status,
                // Urutan: accessor nama_tampil (BE-1, dari mahasiswa/dosen), kolom name, lalu email.
                'actor' => $t->actor ? ($t->actor->nama_tampil ?? $t->actor->name ?? $t->actor->email ?? 'Pengguna') : 'Sistem',
                'note' => $t->catatan_revisi ?? '',
            ]);
    }
}
