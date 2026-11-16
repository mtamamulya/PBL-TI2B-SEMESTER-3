<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('berita', function (Blueprint $table) {
            $table->string('status', 32)->default('draft')->change();
            $table->foreignId('id_kategori')->nullable()->after('id_user')
                ->constrained('kategori', 'id_kategori')->nullOnDelete();
            $table->index(['id_user', 'status']);
        });

        DB::table('berita')->whereIn('status', ['Draft', 'draf', 'draft'])->update(['status' => 'draft']);
        DB::table('berita')->whereIn('status', ['Publish', 'publish'])->update(['status' => 'publish']);
    }

    public function down(): void
    {
        DB::table('berita')->whereNotIn('status', ['draft', 'publish'])->update(['status' => 'draft']);

        Schema::table('berita', function (Blueprint $table) {
            $table->dropForeign(['id_user']);
            $table->dropForeign(['id_kategori']);
            $table->dropIndex(['id_user', 'status']);
            $table->dropColumn('id_kategori');
            $table->enum('status', ['publish', 'draft'])->default('draft')->change();
        });

        Schema::table('berita', function (Blueprint $table) {
            $table->foreign('id_user')->references('id_user')->on('users')->restrictOnDelete();
        });
    }
};
