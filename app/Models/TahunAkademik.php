<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TahunAkademik extends Model
{
    protected $table = 'tahun_akademik';

    protected $primaryKey = 'id_tahun_akademik';

    public $timestamps = false;

    protected $fillable = [
        'tahun_akademik',
        'status',
    ];

    /**
     * Tambah tahun akademik baru sebagai satu-satunya yang aktif,
     * dan otomatis menonaktifkan seluruh tahun akademik sebelumnya.
     */
    public static function buatDanAktifkan(string $labelTahunAkademik): self
    {
        return DB::transaction(function () use ($labelTahunAkademik) {
            static::query()->update(['status' => 0]);

            return static::create([
                'tahun_akademik' => $labelTahunAkademik,
                'status' => 1,
            ]);
        });
    }
}