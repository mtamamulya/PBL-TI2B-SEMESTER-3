<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->string('status_mahasiswa', 30)
                ->default('Aktif')
                ->after('tahun_masuk');
        });

        // Sinkronkan data lama berdasarkan status akun di tabel users
        DB::table('mahasiswa')
            ->join('users', 'users.id_user', '=', 'mahasiswa.id_user')
            ->where('users.status', false)
            ->update(['mahasiswa.status_mahasiswa' => 'Cuti']);
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropColumn('status_mahasiswa');
        });
    }
};
