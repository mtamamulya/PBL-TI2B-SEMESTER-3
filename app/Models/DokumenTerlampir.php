<?php

namespace App\Models;

use App\Models\Models\BackendKel3\DokumenSurat;
use Illuminate\Database\Eloquent\Model;

class DokumenTerlampir extends Model
{
    protected $table = 'dokumen_terlampirs';

    protected $fillable = [
        'dokumen_surat_id',
        'nama_file',
        'jenis_dokumen',
        'file_path',
    ];

    public function dokumenSurat()
    {
        return $this->belongsTo(DokumenSurat::class, 'dokumen_surat_id');
    }
}