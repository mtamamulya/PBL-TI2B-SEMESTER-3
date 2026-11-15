<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Kegiatan extends Model
{
    protected $table = 'kegiatan';

    protected $primaryKey = 'id_kegiatan';

    protected $fillable = [
        'id_kategori', 'id_user', 'id_skim', 'id_kelompok_bidang',
        'nama_kegiatan', 'nomor_sk', 'tanggal_sk',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sk' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $activity) {
            if ($activity->isDirty('id_kategori') && $activity->detail()->exists()) {
                throw ValidationException::withMessages(['id_kategori' => 'Kategori kegiatan yang sudah memiliki jawaban tidak dapat langsung diubah.']);
            }
        });
    }

    public function statusTerkini(): HasOne
    {
        return $this->hasOne(Timeline::class, 'id_kegiatan', 'id_kegiatan')
            ->latestOfMany('tanggal_status');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(Timeline::class, 'id_kegiatan', 'id_kegiatan')
            ->orderBy('tanggal_status')->orderBy('id_timeline');
    }

    public function scopeUntukUser(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $q) => $q->where('id_user', $user->getKey())
            ->orWhereHas('anggota', fn (Builder $q) => $q->where('id_user', $user->getKey())));
    }

    public function scopeUntukPrestasi(Builder $query, ?User $pemilik): Builder
    {
        return $query->untukUser($pemilik)
            ->whereHas('statusTerkini', fn (Builder $status) => $status->where('status', 'diterima'));
    }

    /** Satu baris per kegiatan, termasuk kegiatan yang belum memiliki tanggal. */
    public static function tabelDenganTanggal(): QueryBuilder
    {
        $kegiatan = DB::table('kegiatan')->select('kegiatan.*');
        foreach (['tanggal_mulai', 'tanggal_selesai'] as $input) {
            $tanggal = DB::table('detail_kegiatan as d')
                ->join('isian_kategori as i', 'i.id_isian_kategori', '=', 'd.id_isian_kategori')
                ->whereColumn('d.id_kegiatan', 'kegiatan.id_kegiatan')
                ->where('i.input', $input)
                ->orderBy('d.id_detail_kegiatan')->limit(1)->select('d.isian');
            $kegiatan->selectSub($tanggal, $input);
        }

        return $kegiatan;
    }

    public function scopeDenganTanggal(Builder $query): Builder
    {
        return $query->fromSub(self::tabelDenganTanggal(), 'kegiatan');
    }

    private function tanggalDetail(string $input): ?Carbon
    {
        $nilai = array_key_exists($input, $this->attributes)
            ? $this->attributes[$input]
            : $this->nilaiIsian($input);

        return $nilai && Carbon::hasFormat($nilai, 'Y-m-d') ? Carbon::createFromFormat('!Y-m-d', $nilai) : null;
    }

    public function getTanggalMulaiAttribute(): ?Carbon
    {
        return $this->tanggalDetail('tanggal_mulai');
    }

    public function getTanggalSelesaiAttribute(): ?Carbon
    {
        return $this->tanggalDetail('tanggal_selesai');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori');
    }

    public function skim(): BelongsTo
    {
        return $this->belongsTo(Skim::class, 'id_skim', 'id_skim');
    }

    public function kelompokBidang(): BelongsTo
    {
        return $this->belongsTo(KelompokBidang::class, 'id_kelompok_bidang', 'id_kelompok_bidang');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailKegiatan::class, 'id_kegiatan', 'id_kegiatan')->orderBy('id_detail_kegiatan');
    }

    public function prestasi(): HasMany
    {
        return $this->hasMany(Prestasi::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }

    public function berita(): HasMany
    {
        return $this->hasMany(Berita::class, 'id_kegiatan', 'id_kegiatan');
    }

    public function nilaiIsian(string $input): ?string
    {
        $details = $this->relationLoaded('detail') ? $this->detail : $this->detail()->with('isianKategori')->get();

        return $details->first(fn ($detail) => $detail->isianKategori?->input === $input)?->isian;
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(Dokumen::class, 'id_kegiatan')->orderBy('id_dokumen');
    }

    public function anggota(): HasMany
    {
        return $this->hasMany(AnggotaKegiatan::class, 'id_kegiatan');
    }
}
