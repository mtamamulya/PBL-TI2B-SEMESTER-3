<?php

namespace App\BackendKel1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Mengisi atribut model hanya bila kolomnya ada di tabel (kolom opsional yang ditambahkan belakangan). */
trait IsiKolomAda
{
    /** @param  array<string, mixed>  $nilai  kolom => nilai */
    protected function isiKolomAda(Model $model, array $nilai): void
    {
        foreach ($nilai as $kolom => $isi) {
            if (Schema::hasColumn($model->getTable(), $kolom)) {
                $model->setAttribute($kolom, $isi);
            }
        }
    }
}
