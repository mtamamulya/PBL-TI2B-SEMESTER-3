<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('berita', function (Blueprint $table) {
            $table->foreignId('id_prestasi')->nullable()
                ->constrained('prestasi', 'id_prestasi')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('berita', function (Blueprint $table) {
            $table->dropForeign(['id_prestasi']);
            $table->dropColumn('id_prestasi');
        });
    }
};
