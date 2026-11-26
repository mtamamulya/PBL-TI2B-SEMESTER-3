<?php

namespace App\BackendKel2\Workflow;

use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvalidTransition extends DomainException
{
    public static function between(WorkflowDefinition $def, string $from, string $to): self
    {
        return new self("Status '{$def->label($from)}' tidak bisa diubah menjadi '{$def->label($to)}'.");
    }

    public static function notStarted(string $workflow): self
    {
        return new self("Alur '{$workflow}' belum dimulai untuk data ini.");
    }

    /** API menerima JSON 422, halaman Blade kembali ke form dengan pesan error. */
    public function render(Request $request): Response|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withErrors(['status' => $this->getMessage()]);
    }
}
