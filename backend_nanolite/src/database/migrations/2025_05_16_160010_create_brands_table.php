<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();

            // Relasi ke companies
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained('companies')
                  ->nullOnDelete();

            $table->string('name');                   // Nama brand
            $table->text('deskripsi')->nullable();    // Deskripsi
            $table->string('image')->nullable();      // Gambar brand
             // Status aktif atau non-aktif
            $table->enum('status', ['active', 'non-active'])->default('active');
            $table->timestamps();


        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
