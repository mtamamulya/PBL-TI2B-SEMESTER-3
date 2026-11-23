<?php

namespace App\BackendKel2\Workflow;

/** Dipasang di model yang statusnya dikelola mesin workflow (Kegiatan, Berita, dst). */
interface Workflowable
{
    /** Kunci alur, harus terdaftar di WorkflowServiceProvider (mis. 'kegiatan'). */
    public static function workflowKey(): string;

    /** ID pemilik data, dipakai untuk mengecek pelaku bertipe Actor::OWNER. */
    public function workflowOwnerId(): int|string|null;
}
