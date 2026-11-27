<?php

namespace App\Http\Controllers;

/** Kurikulum TRK untuk kaprodi: logika sama dengan admin (lewat API), hanya tampilannya berbeda. */
class KaprodiKurikulumController extends AdminKurikulumController
{
    protected string $tampilan = 'kaprodi.kurikulum';
}
