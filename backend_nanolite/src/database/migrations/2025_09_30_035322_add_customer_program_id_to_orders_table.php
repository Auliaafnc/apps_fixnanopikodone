<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // kalau belum ada, tambahkan setelah customer_id biar rapi
            if (!Schema::hasColumn('orders', 'customer_program_id')) {
                $table->foreignId('customer_program_id')
                    ->nullable()
                    ->constrained('customer_programs')
                    ->nullOnDelete(); // kalau program dihapus, set NULL
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'customer_program_id')) {
                $table->dropConstrainedForeignId('customer_program_id');
            }
        });
    }
};
