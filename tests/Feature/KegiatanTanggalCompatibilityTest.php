<?php

namespace Tests\Feature;

use App\Models\Kegiatan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KegiatanTanggalCompatibilityTest extends TestCase
{
    public function test_detail_dates_work_with_and_without_legacy_date_columns(): void
    {
        // A dedicated in-memory connection avoids modifying application data.
        $original = DB::getDefaultConnection();
        config(['database.connections.date_compatibility' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::setDefaultConnection('date_compatibility');

        try {
            Schema::create('kegiatan', function (Blueprint $table) {
                $table->id('id_kegiatan');
                $table->string('nama_kegiatan');
            });
            Schema::create('isian_kategori', function (Blueprint $table) {
                $table->id('id_isian_kategori');
                $table->string('input');
            });
            Schema::create('detail_kegiatan', function (Blueprint $table) {
                $table->id('id_detail_kegiatan');
                $table->unsignedBigInteger('id_kegiatan');
                $table->unsignedBigInteger('id_isian_kategori');
                $table->string('isian');
            });
            DB::table('kegiatan')->insert([
                ['id_kegiatan' => 1, 'nama_kegiatan' => 'Dengan tanggal'],
                ['id_kegiatan' => 2, 'nama_kegiatan' => 'Tanpa tanggal'],
            ]);
            DB::table('isian_kategori')->insert([
                ['id_isian_kategori' => 1, 'input' => 'tanggal_mulai'],
                ['id_isian_kategori' => 2, 'input' => 'tanggal_selesai'],
            ]);
            DB::table('detail_kegiatan')->insert([
                ['id_kegiatan' => 1, 'id_isian_kategori' => 1, 'isian' => '2026-11-15'],
                ['id_kegiatan' => 1, 'id_isian_kategori' => 2, 'isian' => '2026-11-16'],
            ]);

            foreach ([false, true] as $legacy) {
                if ($legacy) {
                    Schema::table('kegiatan', function (Blueprint $table) {
                        $table->date('tanggal_mulai')->nullable();
                        $table->date('tanggal_selesai')->nullable();
                    });
                    DB::table('kegiatan')->update(['tanggal_mulai' => '2020-01-01', 'tanggal_selesai' => '2020-01-02']);
                }
                $rows = Kegiatan::denganTanggal()->orderBy('id_kegiatan')->get();
                $this->assertCount(2, $rows);
                $this->assertSame('2026-11-15', $rows[0]->tanggal_mulai->toDateString());
                $this->assertSame('2026-11-16', $rows[0]->tanggal_selesai->toDateString());
                $this->assertNull($rows[1]->tanggal_mulai);
                $this->assertNull($rows[1]->tanggal_selesai);
                // SQLite accepts duplicate output names; inspect the projection too
                // so this regression also protects TiDB's stricter derived tables.
                $this->assertSame(['kegiatan.id_kegiatan', 'kegiatan.nama_kegiatan'], array_slice(Kegiatan::tabelDenganTanggal()->columns, 0, 2));
                $this->assertCount(4, Kegiatan::tabelDenganTanggal()->columns);
                $this->assertSame(1, Kegiatan::denganTanggal()->whereYear('tanggal_mulai', 2026)->count());
            }
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('date_compatibility');
        }
    }
}
