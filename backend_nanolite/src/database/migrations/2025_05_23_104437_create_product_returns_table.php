<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_returns', function (Blueprint $table) {
            $table->id();

            $table->string('no_return')->unique();

            // Relasi ke company (opsional)
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            // Relasi ke customer category (opsional)
            $table->foreignId('customer_categories_id')
                ->nullable()
                ->constrained('customer_categories')
                ->nullOnDelete();

            // Relasi ke customer dan employee (wajib)
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');

            $table->foreignId('department_id')
                  ->constrained('departments')
                  ->cascadeOnDelete();

            // Informasi kontak dan alamat
            $table->string('phone');
            $table->json('address');

            // Alasan return dan catatan tambahan
            $table->text('reason')->comment('Alasan Return');
            $table->text('note')->nullable()->comment('Catatan Tambahan');

            // Jika berupa uang
            $table->decimal('amount', 15, 2)->nullable()->comment('Nominal jika type = money');

            // Bukti gambar
            $table->json('image')->nullable();

            // Produk yang dikembalikan (format JSON)
            $table->json('products')->comment('Detail produk JSON');

            // ===== STATUS UTAMA =====
            $table->enum('status_pengajuan', ['pending','approved','rejected'])
                ->default('pending')
                ->comment('Status Pengajuan Return');

            $table->enum('status_product', ['pending','ready_stock','sold_out', 'rejected'])
                ->default('pending')
                ->comment('Status Ketersediaan Produk');

            $table->enum('status_return', [
                'pending','confirmed','processing','on_hold',
                'delivered','completed','cancelled','rejected'
            ])->default('pending')
              ->comment('Status Proses Return');

            // ===== KOMENTAR KHUSUS (ADMIN) =====
            $table->text('rejection_comment')->nullable()->comment('Komentar Admin saat Rejected');
            $table->foreignId('rejected_by')->nullable()
                ->constrained('employees')->nullOnDelete();


            $table->text('sold_out_comment')->nullable()->comment('Komentar Admin saat Sold Out');
            $table->foreignId('sold_out_by')->nullable()
            ->constrained('employees')->nullOnDelete();
            
            $table->text('on_hold_comment')->nullable()->comment('Komentar Admin saat On Hold');
            $table->timestamp('on_hold_until')->nullable()->comment('Tanggal batas hold (opsional)');
            $table->foreignId('on_hold_by')->nullable()
                ->constrained('employees')->nullOnDelete();

            $table->text('cancelled_comment')->nullable()->comment('Komentar Admin saat Cancelled');
            $table->foreignId('cancelled_by')->nullable()
                ->constrained('employees')->nullOnDelete();

            // ===== BUKTI DELIVERED (SALES / KARYAWAN) =====
            $table->json('delivery_images')->nullable()
                ->comment('Daftar gambar bukti pesanan sudah sampai (upload oleh karyawan)');
            $table->timestamp('delivered_at')->nullable()
                ->comment('Waktu konfirmasi pesanan sampai oleh karyawan');
            $table->foreignId('delivered_by')->nullable()
                ->constrained('employees')
                ->nullOnDelete()
                ->comment('Karyawan yang mengunggah bukti delivered');

            // File export
            $table->string('return_file')->nullable()->comment('Path file PDF return di storage/public');
            $table->string('return_excel')->nullable()->comment('Path file Excel return di storage/public');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_returns');
    }
};
