<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Timeline extends Model
{
    protected $table = 'timeline';

    protected $primaryKey = 'id_timeline';

    protected $fillable = ['id_kegiatan', 'status', 'tanggal_status', 'catatan_revisi'];

    protected function casts(): array
    {
        return [
            'tanggal_status' => 'datetime',
        ];
    }

    public const BATAS_NOTIFIKASI_WEB = 50;

    public function pembaca(): HasMany
    {
        return $this->hasMany(NotifikasiDibaca::class, 'id_timeline', 'id_timeline');
    }

    public static function notifikasiUntuk(User $user, int $batas): Builder
    {
        return self::query()
            ->whereHas('kegiatan', fn ($q) => $q->untukUser($user))
            ->withExists(['pembaca as sudah_dibaca' => fn ($q) => $q->where('id_user', $user->getKey())])
            ->orderByDesc('tanggal_status')->orderByDesc('id_timeline')->limit($batas);
    }

    public function belumDibaca(): bool
    {
        return ! $this->sudah_dibaca && ($this->tanggal_status ?? $this->created_at)?->gt(now()->subDays(30));
    }

    public static function jumlahBelumDibaca(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return self::notifikasiUntuk($user, self::BATAS_NOTIFIKASI_WEB)
            ->get()->filter(fn (self $item) => $item->belumDibaca())->count();
    }

    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(Kegiatan::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function buktiHistory(): HasMany
    {
        return $this->hasMany(BuktiHistory::class, 'id_timeline', 'id_timeline');
    }
}
