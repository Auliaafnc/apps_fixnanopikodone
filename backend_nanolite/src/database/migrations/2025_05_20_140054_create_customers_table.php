<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();   
            $table->index(['email']);              

            $table->foreignId('company_id') 
                  ->nullable()
                  ->constrained('companies')
                  ->nullOnDelete();

            $table->foreignId('customer_categories_id')
                  ->constrained('customer_categories')
                  ->cascadeOnDelete();

            $table->json('address');
            $table->string('gmaps_link')->nullable();

            $table->foreignId('department_id')
                  ->constrained('departments')
                  ->cascadeOnDelete();

            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->cascadeOnDelete();

            $table->foreignId('customer_program_id')
                  ->nullable()
                  ->constrained('customer_programs')
                  ->nullOnDelete();

            $table->string('jumlah_program')->nullable();
            $table->string('reward_point')->nullable();
            $table->json('image')->nullable();

            $table->enum('status_pengajuan', ['pending','approved','rejected'])->default('pending');
             // Status aktif atau non-aktif
            $table->enum('status', ['active', 'non-active', 'pending'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
