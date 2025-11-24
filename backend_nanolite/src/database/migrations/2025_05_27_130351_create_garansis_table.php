<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garansis', function (Blueprint $table) {
            $table->id();

            // Nomor garansi unik
            $table->string('no_garansi')->unique();

            // Relasi ke company (nullable)
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            // Relasi ke customer category (nullable)
            $table->foreignId('customer_categories_id')
                ->nullable()
                ->constrained('customer_categories')
                ->nullOnDelete();

            // Relasi ke employee & department
            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('department_id')
                ->constrained('departments')
                ->cascadeOnDelete();

            // Relasi ke customer
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            // Data alamat & kontak
            $table->json('address');
            $table->string('phone');

            // Detail produk (brand, kategori, produk, warna, quantity)
            $table->json('products')->comment('Detail produk JSON');

            // Tanggal pembelian & klaim
            $table->date('purchase_date')->comment('Tanggal Pembelian');
            $table->date('claim_date')->comment('Tanggal Klaim Garansi');

            // Alasan & catatan
            $table->text('reason')->comment('Alasan Mengajukan Garansi');
            $table->text('note')->nullable()->comment('Catatan Tambahan');

            // Foto bukti tambahan (misal invoice/produk rusak)
            $table->json('image')->nullable();

            // ===== STATUS UTAMA =====
            $table->enum('status_pengajuan', ['pending','approved','rejected'])
                ->default('pending')
                ->comment('Status Pengajuan Garansi');

            $table->enum('status_product', ['pending','ready_stock','sold_out', 'rejected'])
                ->default('pending')
                ->comment('Status Ketersediaan Produk');

            $table->enum('status_garansi', [
                'pending','confirmed','processing','on_hold',
                'delivered','completed','cancelled','rejected'
            ])->default('pending')
              ->comment('Status Proses Garansi');

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

            // File PDF & Excel opsional
            $table->string('garansi_file')->nullable()->comment('Path file PDF garansi di storage/public');
            $table->string('garansi_excel')->nullable()->comment('Path file Excel garansi di storage/public');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garansis');
    }
};