<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Relasi ke tabel companies (nullable dan null on delete)
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained('companies')
                  ->nullOnDelete();

            // Relasi ke tabel departments
            $table->foreignId('department_id')
                  ->constrained('departments')
                  ->cascadeOnDelete();

            // Informasi dasar karyawan
            $table->string('name');
            $table->string('email')->unique()->nullable();
            $table->string('phone');
            $table->json('address')->nullable();
            $table->string('photo')->nullable();

            // Status aktif atau non-aktif
            $table->enum('status', ['active', 'non-active'])->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
