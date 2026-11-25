<?php

namespace App\BackendKel2\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Tabel timeline (ERD final): satu baris per perubahan status kegiatan.
 * Kolom id_user (pelaku) adalah tambahan yang diusulkan, ERD asli belum punya.
 * Append-only: tidak boleh diubah atau dihapus lewat model.
 */
class Timeline extends Model
{
    public $timestamps = false;

    protected $table = 'timeline';

    protected $primaryKey = 'id_timeline';

    protected $fillable = ['id_kegiatan', 'status', 'tanggal_status', 'catatan_revisi', 'id_user'];

    protected function casts(): array
    {
        return ['tanggal_status' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat status tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Riwayat status tidak boleh dihapus.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
