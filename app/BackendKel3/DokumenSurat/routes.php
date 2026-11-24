<?php

use App\Http\Controllers\BackendKel3\DokumenSurat\DokumenSuratController;
use Illuminate\Support\Facades\Route;

foreach (['kegiatan', 'pengajuan'] as $prefix) {
    Route::get("$prefix/{id}/dokumen", [DokumenSuratController::class, 'index'])->whereNumber('id')->name("$prefix.dokumen.index");
    Route::post("$prefix/{id}/dokumen", [DokumenSuratController::class, 'store'])->whereNumber('id')->name("$prefix.dokumen.store");
    Route::delete("$prefix/{id}/dokumen/{dokumenId}", [DokumenSuratController::class, 'destroy'])->whereNumber(['id', 'dokumenId'])->name("$prefix.dokumen.destroy");
}

Route::get('pengajuan/{id}/surat', [DokumenSuratController::class, 'showSurat'])->whereNumber('id')->name('pengajuan.surat');
Route::get('pengajuan/{id}/surat/pdf-final', [DokumenSuratController::class, 'exportPdfFinal'])->whereNumber('id')->name('pengajuan.surat.pdf-final');
Route::post('pengajuan/{id}/surat/tanda-tangan', [DokumenSuratController::class, 'simpanTandaTangan'])->whereNumber('id')->name('pengajuan.surat.tanda-tangan');
