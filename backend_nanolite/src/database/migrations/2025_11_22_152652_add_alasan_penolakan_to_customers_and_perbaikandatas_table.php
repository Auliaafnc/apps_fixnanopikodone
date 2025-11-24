<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('alasan_penolakan')
                ->nullable()
                ->after('status_pengajuan');
        });

        Schema::table('perbaikandatas', function (Blueprint $table) {
            $table->text('alasan_penolakan')
                ->nullable()
                ->after('status_pengajuan');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('alasan_penolakan');
        });

        Schema::table('perbaikandatas', function (Blueprint $table) {
            $table->dropColumn('alasan_penolakan');
        });
    }
};
