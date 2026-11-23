<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OtpSchemaReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_otp_rows_are_preserved_and_expiry_does_not_change_on_updates(): void
    {
        Schema::drop('otp_codes');
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id('id_otp_code');
            $table->unsignedBigInteger('id_user');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('expires_at');
            $table->dateTime('last_sent_at');
            $table->timestamp('consumed_at')->nullable();
        });
        DB::table('otp_codes')->insert([
            'id_user' => 1, 'code_hash' => 'existing-hash',
            'expires_at' => '2026-11-22 12:05:00', 'last_sent_at' => '2026-11-22 12:00:00',
        ]);
        $migration = require database_path('migrations/2026_11_22_000001_reconcile_existing_otp_codes.php');
        $migration->up();
        $migration->up();
        DB::table('otp_codes')->update(['attempts' => 1]);
        $this->assertDatabaseHas('otp_codes', [
            'id_user' => 1, 'code_hash' => 'existing-hash', 'tujuan' => 'lupa_sandi',
            'expires_at' => '2026-11-22 12:05:00', 'last_sent_at' => '2026-11-22 12:00:00',
            'attempts' => 1,
        ]);
        $this->assertTrue(Schema::hasIndex('otp_codes', 'otp_codes_id_user_tujuan_consumed_at_index'));
        DB::table('otp_codes')->update(['last_sent_at' => null]);
        $this->assertDatabaseCount('otp_codes', 1);
    }
}
