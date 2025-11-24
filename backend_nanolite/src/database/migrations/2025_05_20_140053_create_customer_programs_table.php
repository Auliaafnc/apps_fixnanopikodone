<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_programs', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel companies (opsional)
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained('companies')
                  ->nullOnDelete();

            $table->string('name')->nullable();       // Nama program
            $table->text('deskripsi')->nullable();    // Deskripsi program
             // Status aktif atau non-aktif
            $table->enum('status', ['active', 'non-active'])->default('active');

            $table->date('starts_at')->nullable()->comment('Tanggal mulai program');
            $table->date('ends_at')->nullable()->comment('Tanggal berakhir program');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_programs');
    }
};
