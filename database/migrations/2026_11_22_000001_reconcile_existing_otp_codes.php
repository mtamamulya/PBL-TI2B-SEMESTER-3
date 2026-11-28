<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Instalasi lama sudah mencatat migration create, tetapi belum memiliki tujuan.
        if (! Schema::hasColumn('otp_codes', 'tujuan')) {
            Schema::table('otp_codes', function (Blueprint $table) {
                $table->string('tujuan', 30)->nullable();
            });
            DB::table('otp_codes')->whereNull('tujuan')->update(['tujuan' => 'lupa_sandi']);
            Schema::table('otp_codes', function (Blueprint $table) {
                $table->string('tujuan', 30)->nullable(false)->change();
            });
        }

        Schema::table('otp_codes', function (Blueprint $table) {
            // DATETIME mencegah default/ON UPDATE implisit TIMESTAMP pada MariaDB.
            $table->dateTime('expires_at')->default(null)->change();
            $table->dateTime('last_sent_at')->nullable()->default(null)->change();
        });

        if (! Schema::hasIndex('otp_codes', 'otp_codes_id_user_tujuan_consumed_at_index')) {
            Schema::table('otp_codes', function (Blueprint $table) {
                $table->index(['id_user', 'tujuan', 'consumed_at']);
            });
        }
    }

    public function down(): void
    {
        // Koreksi kompatibilitas sengaja dipertahankan: kode develop membutuhkan tujuan.
        // Menghapus kolom ini saat rollback akan menghilangkan klasifikasi OTP lama.
    }
};
