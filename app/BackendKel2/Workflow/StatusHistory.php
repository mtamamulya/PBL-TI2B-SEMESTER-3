<?php

namespace App\BackendKel2\Workflow;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Satu baris riwayat. Append-only: tidak boleh diubah atau dihapus lewat model. */
class StatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'status_histories';

    protected $fillable = ['workflow', 'subject_id', 'from_status', 'status', 'actor_id', 'note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat status tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Riwayat status tidak boleh dihapus.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
