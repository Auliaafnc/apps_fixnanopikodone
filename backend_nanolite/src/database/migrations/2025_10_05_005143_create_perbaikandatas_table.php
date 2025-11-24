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
        Schema::create('perbaikandatas', function (Blueprint $table) {
             $table->id();

            // Relasi ke company (nullable)
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            // Relasi ke employee & department
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();
            
            // Relasi ke customer category (nullable)
            $table->foreignId('customer_categories_id')
                ->nullable()
                ->constrained('customer_categories')
                ->nullOnDelete();

            // Relasi ke customer
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('pilihan_data'); 

            $table->text('data_baru')->nullable()->comment('Data yang diperbarui');

            $table->json('address')->nullable()
                    ->comment('Alamat baru jika ada perubahan alamat');

            // Foto bukti tambahan (misal invoice/produk rusak)
            $table->json('image')->nullable();

            // ===== STATUS UTAMA =====
            $table->enum('status_pengajuan', ['pending','approved','rejected'])->default('pending');

            $table->timestamps();
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perbaikandatas');
    }
};
