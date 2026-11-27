<?php

namespace App\BackendKel2\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use LogicException;

/**
 * Satu-satunya pintu untuk mengubah status. Modul lain tidak boleh menulis ke tabel
 * timeline atau kolom status langsung. Semua perubahan terjadi dalam satu transaksi dengan
 * baris data dikunci, jadi dua reviewer tidak bisa mengubah status bersamaan.
 */
class WorkflowEngine
{
    public function __construct(private readonly WorkflowRegistry $registry) {}

    public function definition(string $workflow): WorkflowDefinition
    {
        return $this->registry->for($workflow);
    }

    /** Memulai alur untuk data baru. Panggil dari proses pembuatan data. */
    public function start(Model&Workflowable $subject, ?User $actor, ?string $status = null, ?string $note = null): ?Model
    {
        $workflow = $subject::workflowKey();
        $def = $this->registry->for($workflow);
        $status ??= $def->defaultStart();

        if (! $def->canStartAt($status)) {
            throw new InvalidArgumentException("Alur '{$workflow}' tidak boleh dimulai di status '{$status}'.");
        }

        return DB::transaction(function () use ($subject, $actor, $workflow, $status, $note) {
            if ($this->registry->store($workflow)->hasStarted($subject)) {
                throw new LogicException("Alur '{$workflow}' sudah dimulai untuk data ini.");
            }

            return $this->record($subject, $workflow, null, $status, $actor, $note);
        });
    }

    public function transition(Model&Workflowable $subject, string $to, ?User $actor, ?string $note = null): ?Model
    {
        $workflow = $subject::workflowKey();
        $def = $this->registry->for($workflow);
        $store = $this->registry->store($workflow);

        return DB::transaction(function () use ($subject, $to, $actor, $note, $workflow, $def, $store) {
            // Kunci baris data supaya perubahan status berurutan.
            $locked = $subject->newQuery()->whereKey($subject->getKey())->lockForUpdate()->firstOrFail();

            $from = $store->current($locked);
            if ($from === null) {
                throw InvalidTransition::notStarted($workflow);
            }

            $rule = $def->rule($from, $to) ?? throw InvalidTransition::between($def, $from, $to);

            if (! $this->permits($rule['by'], $locked, $actor)) {
                throw new TransitionForbidden;
            }

            if ($rule['note'] && blank($note)) {
                throw ValidationException::withMessages(['note' => 'Catatan wajib diisi untuk perubahan status ini.']);
            }

            return $this->record($locked, $workflow, $from, $to, $actor, $note);
        });
    }

    public function can(Model&Workflowable $subject, string $to, ?User $actor): bool
    {
        $workflow = $subject::workflowKey();
        $from = $this->registry->store($workflow)->current($subject);
        $rule = $from === null ? null : $this->registry->for($workflow)->rule($from, $to);

        return $rule !== null && $this->permits($rule['by'], $subject, $actor);
    }

    /**
     * Aksi yang boleh dilakukan pelaku saat ini, untuk menampilkan tombol di UI.
     *
     * @return array<int,array{to:string,label:string,requires_note:bool}>
     */
    public function availableTransitions(Model&Workflowable $subject, ?User $actor): array
    {
        $workflow = $subject::workflowKey();
        $def = $this->registry->for($workflow);
        $from = $this->registry->store($workflow)->current($subject);

        if ($from === null) {
            return [];
        }

        $out = [];
        foreach ($def->transitionsFrom($from) as $rule) {
            if ($this->permits($rule['by'], $subject, $actor)) {
                $out[] = ['to' => $rule['to'], 'label' => $def->label($rule['to']), 'requires_note' => $rule['note']];
            }
        }

        return $out;
    }

    public function currentStatus(string $workflow, Model $subject): ?string
    {
        return $this->registry->store($workflow)->current($subject);
    }

    /**
     * Jumlah data per kode status untuk kueri dasar (mis. pengajuan milik satu mahasiswa).
     *
     * @return array<string,int>
     */
    public function countByStatus(Builder $base): array
    {
        $model = $base->getModel();
        if (! $model instanceof Workflowable) {
            throw new InvalidArgumentException('Model harus mengimplementasikan Workflowable.');
        }

        $counts = $this->registry->store($model::workflowKey())->countByStatus($base);
        $definition = $this->registry->for($model::workflowKey());
        $ordered = [];

        foreach ($definition->statuses() as $status) {
            if (array_key_exists($status, $counts)) {
                $ordered[$status] = $counts[$status];
                unset($counts[$status]);
            }
        }

        return [...$ordered, ...$counts];
    }

    /** Sama seperti countByStatus tetapi dikunci dengan label tampilan (format yang dipakai Blade). */
    public function countByLabel(Builder $base): array
    {
        $model = $base->getModel();
        $def = $this->registry->for($model::workflowKey());

        $out = [];
        foreach ($this->countByStatus($base) as $code => $total) {
            $out[$def->has($code) ? $def->label($code) : $code] = $total;
        }

        return $out;
    }

    private function record(Model $subject, string $workflow, ?string $from, string $to, ?User $actor, ?string $note): ?Model
    {
        $record = $this->registry->store($workflow)->write($subject, $from, $to, $actor, $note);

        event(new StatusChanged($subject, $workflow, $from, $to, $actor, filled($note) ? trim($note) : null, $record));

        return $record;
    }

    /** @param  array<int,string>  $allowed */
    private function permits(array $allowed, Model $subject, ?User $actor): bool
    {
        if ($actor === null) {
            return in_array(Actor::SYSTEM, $allowed, true);
        }

        if (
            in_array(Actor::OWNER, $allowed, true)
            && $subject instanceof Workflowable
            && (string) $subject->workflowOwnerId() === (string) $actor->getKey()
        ) {
            return true;
        }

        return in_array(Actor::STAFF, $allowed, true)
            && Gate::forUser($actor)->allows('review-workflow');
    }
}
