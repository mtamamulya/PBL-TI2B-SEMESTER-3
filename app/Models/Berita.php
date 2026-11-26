<?php

namespace App\Models;

use App\BackendKel2\Workflow\HasWorkflow;
use App\BackendKel2\Workflow\Workflowable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Berita extends Model implements Workflowable
{
    use HasWorkflow;

    public const STATUSES = ['draft', 'diajukan', 'direvisi', 'revisi', 'ditolak', 'publish'];

    protected $table = 'berita';

    protected $primaryKey = 'id_berita';

    protected $fillable = [
        'id_kegiatan',
        'id_prestasi',
        'id_user',
        'id_kategori',
        'judul_berita',
        'isi_berita',
        'status',
        'tanggal_publish',
        'sampul',
        'catatan_revisi',
    ];

    protected $casts = [
        'tanggal_publish' => 'datetime',
    ];

    public static function workflowKey(): string
    {
        return 'berita';
    }

    public function workflowOwnerId(): int|string|null
    {
        return $this->id_user;
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori', 'id_kategori');
    }

    public function gambarBerita(): HasMany
    {
        return $this->hasMany(GambarBerita::class, 'id_berita', 'id_berita');
    }
}
