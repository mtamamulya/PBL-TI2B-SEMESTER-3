<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil Prodi: tambah kolom NULLABLE ke tabel `prodi` (kolom lama tidak diubah).
 * Tiap kolom dicek dulu (hasColumn) supaya aman dijalankan ulang di database bersama.
 */
return new class extends Migration
{
    /** @var array<string, array{0:string,1:int|null}> nama kolom => [tipe, panjang] */
    private const KOLOM = [
        'deskripsi' => ['text', null],
        'visi' => ['text', null],
        'misi' => ['json', null],
        'tujuan' => ['json', null],
        'profil_lulusan' => ['json', null],
        'email' => ['string', 100],
        'no_wa' => ['string', 20],
        'alamat' => ['string', 500],
        'instagram' => ['string', 255],
        'youtube' => ['string', 255],
        'tiktok' => ['string', 255],
        'facebook' => ['string', 255],
        'logo' => ['string', 255],
        'logo_footer' => ['string', 255],
    ];

    public function up(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            foreach (self::KOLOM as $nama => [$tipe, $panjang]) {
                if (Schema::hasColumn('prodi', $nama)) {
                    continue;
                }
                $kolom = $panjang ? $table->{$tipe}($nama, $panjang) : $table->{$tipe}($nama);
                $kolom->nullable();
            }
        });
    }

    public function down(): void
    {
        $ada = array_values(array_filter(array_keys(self::KOLOM), fn ($k) => Schema::hasColumn('prodi', $k)));

        if ($ada) {
            Schema::table('prodi', fn (Blueprint $table) => $table->dropColumn($ada));
        }
    }
};
