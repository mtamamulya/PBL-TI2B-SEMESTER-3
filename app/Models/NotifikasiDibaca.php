<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotifikasiDibaca extends Model
{
    protected $table = 'notifikasi_dibaca';

    protected $primaryKey = 'id_notifikasi_dibaca';

    public $timestamps = false;

    protected $fillable = ['id_user', 'id_timeline', 'dibaca_pada'];

    protected function casts(): array
    {
        return ['dibaca_pada' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function timeline(): BelongsTo
    {
        return $this->belongsTo(Timeline::class, 'id_timeline', 'id_timeline');
    }
}
