<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();

            // Jika departemen ini milik suatu perusahaan (relasi opsional)
            $table->foreignId('company_id')->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->string('name'); // Nama departemen
            $table->enum('status', ['active', 'non-active'])->default('active');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
