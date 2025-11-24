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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // Relasi ke brand
            $table->foreignId('brand_id')
                ->constrained('brands')
                ->cascadeOnDelete();

            // Relasi ke company (optional)
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            // Kolom informasi kategori
            $table->string('name');                // Nama kategori
            $table->text('deskripsi')->nullable(); // Deskripsi (boleh kosong)
            $table->string('image')->nullable();   // Path atau URL gambar
             // Status aktif atau non-aktif
            $table->enum('status', ['active', 'non-active'])->default('active');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
