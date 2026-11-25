<?php

namespace App\BackendKel2\Workflow;

/** Jenis pelaku yang boleh menjalankan sebuah transisi. */
final class Actor
{
    /** Pemilik data (mis. mahasiswa pembuat pengajuan). */
    public const OWNER = 'owner';

    /** Reviewer atau admin. Dicek lewat Gate 'review-workflow'. */
    public const STAFF = 'staff';

    /** Proses otomatis tanpa pengguna (scheduler, listener). */
    public const SYSTEM = 'system';

    public const ALL = [self::OWNER, self::STAFF, self::SYSTEM];
}
