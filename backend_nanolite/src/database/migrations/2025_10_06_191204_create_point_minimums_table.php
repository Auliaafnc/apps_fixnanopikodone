<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_minimums', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['reward', 'program']); // jenis minimum
            $table->foreignId('program_id')->nullable()
                  ->constrained('customer_programs') // ganti kalau nama tabel program berbeda
                  ->nullOnDelete();
            $table->unsignedBigInteger('min_amount'); // contoh: 2000000 (Rp 2.000.000)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Hindari duplikasi: satu baris per kombinasi type+program
            $table->unique(['type', 'program_id'], 'uniq_type_program');
            $table->index(['type', 'is_active']);
        });

        // Seed default minimum Reward (opsional)
        DB::table('point_minimums')->updateOrInsert(
            ['type' => 'reward', 'program_id' => null],
            ['min_amount' => 1000000, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('point_minimums');
    }
};
