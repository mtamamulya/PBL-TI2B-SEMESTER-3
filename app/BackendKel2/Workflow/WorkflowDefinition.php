<?php

namespace App\BackendKel2\Workflow;

use InvalidArgumentException;

/**
 * Definisi satu alur status: daftar status, label tampilan, transisi yang sah,
 * siapa yang boleh menjalankannya, dan status mana yang masih boleh diedit/dihapus.
 *
 * Kode status (disimpan di database) dipisahkan dari label (ditampilkan di UI),
 * jadi UI boleh memakai "Perlu revisi" sementara kodenya tetap "direvisi".
 */
final class WorkflowDefinition
{
    /** @var array<int, array{from:string,to:string,by:array<int,string>,note:bool}> */
    private array $transitions = [];

    /**
     * @param  array<string,string>  $labels  kode => label tampilan
     * @param  array<int,string>  $startable  status yang boleh dipakai saat alur dimulai (yang pertama = default)
     * @param  array<int,array{from:string,to:string,by:array<int,string>,note?:bool}>  $transitions
     * @param  array<int,string>  $editable  status yang masih boleh diedit
     * @param  array<int,string>  $deletable  status yang masih boleh dihapus
     */
    public function __construct(
        public readonly string $key,
        private readonly array $labels,
        private readonly array $startable,
        array $transitions,
        private readonly array $editable = [],
        private readonly array $deletable = [],
    ) {
        if ($startable === []) {
            throw new InvalidArgumentException("Alur '{$key}' harus punya minimal satu status awal.");
        }

        foreach ([...$startable, ...$editable, ...$deletable] as $status) {
            $this->assertKnown($status);
        }

        foreach ($transitions as $t) {
            $this->assertKnown($t['from']);
            $this->assertKnown($t['to']);

            if ($t['by'] === [] || array_diff($t['by'], Actor::ALL) !== []) {
                throw new InvalidArgumentException("Pelaku transisi {$t['from']} -> {$t['to']} tidak valid.");
            }

            $this->transitions[] = [
                'from' => $t['from'],
                'to' => $t['to'],
                'by' => $t['by'],
                'note' => $t['note'] ?? false,
            ];
        }
    }

    /** @return array<int,string> */
    public function statuses(): array
    {
        return array_keys($this->labels);
    }

    public function has(string $status): bool
    {
        return array_key_exists($status, $this->labels);
    }

    public function label(string $status): string
    {
        $this->assertKnown($status);

        return $this->labels[$status];
    }

    /** Kode dari label tampilan (tanpa membedakan huruf besar/kecil). Null kalau tidak ada. */
    public function codeForLabel(string $label): ?string
    {
        foreach ($this->labels as $code => $text) {
            if (mb_strtolower($text) === mb_strtolower(trim($label))) {
                return $code;
            }
        }

        return null;
    }

    public function defaultStart(): string
    {
        return $this->startable[0];
    }

    public function canStartAt(string $status): bool
    {
        return in_array($status, $this->startable, true);
    }

    /** @return array<int,array{from:string,to:string,by:array<int,string>,note:bool}> */
    public function transitionsFrom(string $status): array
    {
        $this->assertKnown($status);

        return array_values(array_filter($this->transitions, fn ($t) => $t['from'] === $status));
    }

    /** @return array{from:string,to:string,by:array<int,string>,note:bool}|null */
    public function rule(string $from, string $to): ?array
    {
        foreach ($this->transitions as $t) {
            if ($t['from'] === $from && $t['to'] === $to) {
                return $t;
            }
        }

        return null;
    }

    public function canTransition(string $from, string $to): bool
    {
        return $this->rule($from, $to) !== null;
    }

    public function requiresNote(string $from, string $to): bool
    {
        return $this->rule($from, $to)['note'] ?? false;
    }

    /** Status akhir = tidak punya transisi keluar. */
    public function isFinal(string $status): bool
    {
        return $this->transitionsFrom($status) === [];
    }

    public function isEditable(string $status): bool
    {
        return in_array($status, $this->editable, true);
    }

    public function isDeletable(string $status): bool
    {
        return in_array($status, $this->deletable, true);
    }

    private function assertKnown(string $status): void
    {
        if (! array_key_exists($status, $this->labels)) {
            throw new InvalidArgumentException("Status '{$status}' tidak dikenal di alur '{$this->key}'.");
        }
    }
}
