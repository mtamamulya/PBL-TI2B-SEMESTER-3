<?php

namespace App\Models\Models\BackendKel3;

use Illuminate\Database\Eloquent\Model;

class DokumenSurat extends Model
{
    protected $table = 'dokumen_surats';

    protected $fillable = [
        'user_id',
        'nama_kegiatan',
        'jenis_kegiatan',
        'kategori',
        'penyelenggara',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
    ];

    public function dokumenTerlampir()
    {
        return $this->hasMany(\App\Models\DokumenTerlampir::class, 'dokumen_surat_id');
    }
}