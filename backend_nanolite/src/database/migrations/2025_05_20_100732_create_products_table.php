<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // Relasi ke company
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained('companies')
                  ->nullOnDelete();

            // Relasi ke brand
            $table->foreignId('brand_id')
                  ->constrained('brands')
                  ->onDelete('cascade');

            // Relasi ke kategori
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->onDelete('cascade');

            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->text('description')->nullable();

            $table->json('colors')->nullable(); // Menyimpan multi warna
            $table->string('image')->nullable(); // URL gambar
             // Status aktif atau non-aktif
            $table->enum('status', ['active', 'non-active'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
