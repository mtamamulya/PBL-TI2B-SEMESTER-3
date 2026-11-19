<?php

namespace App\Http\Controllers;

/** Mata Kuliah untuk kaprodi: logika sama dengan admin (lewat API), hanya tampilan dan nama rute yang berbeda. */
class KaprodiMataKuliahController extends AdminMataKuliahController
{
    protected string $viewDaftar = 'kaprodi.mata-kuliah';

    protected string $viewTambah = 'kaprodi.mata-kuliah-tambah';

    protected string $viewEdit = 'kaprodi.mata-kuliah-edit';

    protected string $rutaDaftar = 'kaprodi.kurikulum.mata-kuliah';
}
