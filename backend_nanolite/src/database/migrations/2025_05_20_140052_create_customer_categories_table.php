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
        Schema::create('customer_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')  // Foreign key ke companies
                  ->nullable()
                  ->constrained('companies')
                  ->nullOnDelete();
            $table->string('name');               // Nama kategori
            $table->text('deskripsi')->nullable(); // Deskripsi (boleh kosong)
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
        Schema::dropIfExists('customer_categories');
    }
};
