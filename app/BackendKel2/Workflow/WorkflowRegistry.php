<?php

namespace App\BackendKel2\Workflow;

use OutOfBoundsException;

/** Daftar alur: tiap alur punya definisi aturan dan tempat penyimpanan status. */
final class WorkflowRegistry
{
    /** @var array<string,WorkflowDefinition> */
    private array $definitions = [];

    /** @var array<string,StatusStore> */
    private array $stores = [];

    public function register(WorkflowDefinition $definition, StatusStore $store): self
    {
        $this->definitions[$definition->key] = $definition;
        $this->stores[$definition->key] = $store;

        return $this;
    }

    public function for(string $key): WorkflowDefinition
    {
        return $this->definitions[$key] ?? throw $this->belumTerdaftar($key);
    }

    public function store(string $key): StatusStore
    {
        return $this->stores[$key] ?? throw $this->belumTerdaftar($key);
    }

    private function belumTerdaftar(string $key): OutOfBoundsException
    {
        return new OutOfBoundsException("Alur '{$key}' belum didaftarkan di WorkflowServiceProvider.");
    }
}
