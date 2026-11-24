<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('otp_codes', 'recipient_email')) {
            Schema::table('otp_codes', function (Blueprint $table) {
                $table->string('recipient_email', 150)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('otp_codes', 'recipient_email')) {
            Schema::table('otp_codes', function (Blueprint $table) {
                $table->dropColumn('recipient_email');
            });
        }
    }
};
