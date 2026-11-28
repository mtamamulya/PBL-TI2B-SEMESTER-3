<?php

namespace App\BackendKel2\Workflow;

use Illuminate\Auth\Access\AuthorizationException;

/** Laravel otomatis mengubahnya menjadi respons 403. */
class TransitionForbidden extends AuthorizationException
{
    public function __construct(string $message = 'Anda tidak berhak mengubah status ini.')
    {
        parent::__construct($message);
    }
}
