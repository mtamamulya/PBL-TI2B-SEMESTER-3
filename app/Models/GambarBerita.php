<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GambarBerita extends Model
{
    protected $table = 'gambar_berita';

    protected $primaryKey = 'id_gambar_berita';

    protected $fillable = [
        'id_berita',
        'id_dokumen',
        'id_user',
        'nama_file',
        'path_gambar',
        'sumber',
    ];

    public function berita(): BelongsTo
    {
        return $this->belongsTo(Berita::class, 'id_berita', 'id_berita');
    }

    public function dokumen(): BelongsTo
    {
        return $this->belongsTo(Dokumen::class, 'id_dokumen', 'id_dokumen');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
