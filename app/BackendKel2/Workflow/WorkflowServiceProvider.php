<?php

namespace App\BackendKel2\Workflow;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WorkflowServiceProvider extends ServiceProvider
{
    /**
     * Nama role (kolom role.nama_role) yang boleh meninjau pengajuan. Samakan dengan data role dari BE-1.
     */
    private const STAFF_ROLES = ['admin', 'reviewer'];

    public function register(): void
    {
        // Alur baru: buat class definisinya, pilih store-nya, lalu daftarkan di sini.
        $this->app->singleton(WorkflowRegistry::class, fn () => (new WorkflowRegistry)
            ->register(KegiatanWorkflow::definition(), new TimelineStore)
            ->register(BeritaWorkflow::definition(), new ColumnStore(
                statusColumn: 'status',
                noteColumn: 'catatan_revisi',
                onEnter: ['publish' => ['tanggal_publish' => fn () => now()]],
            )));

        $this->app->singleton(WorkflowEngine::class);
    }

    public function boot(): void
    {
        // Pengaman sementara sampai BE-1 mendefinisikan aturan role resmi.
        // Gate::define milik BE-1 akan menimpa ini. Jika relasi role tidak ada, hasilnya false (aman).
        if (! Gate::has('review-workflow')) {
            Gate::define('review-workflow', fn ($user) => in_array($user->role?->nama_role, self::STAFF_ROLES, true));
        }
    }
}
