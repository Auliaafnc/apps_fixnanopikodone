<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_category_customer_program', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('customer_categories')
                  ->nullOnDelete();

            $table->foreignId('program_id')
                  ->nullable()
                  ->constrained('customer_programs')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_category_customer_program');
    }
};
