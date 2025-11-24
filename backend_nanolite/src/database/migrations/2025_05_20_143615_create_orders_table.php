<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('no_order')->unique();

            // Relasi penting
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('department_id')
                    ->nullable()  
                  ->constrained('departments')
                  ->nullOnDelete(); 
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); 
            $table->foreignId('customer_categories_id')->nullable()->constrained('customer_categories')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // Kontak & alamat
            $table->string('phone');
            $table->json('address');

            // Diskon
            $table->boolean('diskons_enabled')->default(false);
            $table->decimal('diskon_1', 5, 2)->default(0);
            $table->decimal('diskon_2', 5, 2)->default(0);
            $table->decimal('diskon_3', 5, 2)->default(0);
            $table->decimal('diskon_4', 5, 2)->default(0);
            $table->text('penjelasan_diskon_1')->nullable();
            $table->text('penjelasan_diskon_2')->nullable();
            $table->text('penjelasan_diskon_3')->nullable();
            $table->text('penjelasan_diskon_4')->nullable();
           
            // Detail produk dalam JSON
            $table->json('products')->nullable();

             // Status pembayaran 
            $table->enum('payment_method', ['tempo', 'cash'])->default('tempo');
            $table->date('payment_due_until')->nullable(); // untuk jatuh tempo kalau tempo
            $table->enum('status_pembayaran', ['belum bayar', 'sudah bayar', 'belum lunas', 'sudah lunas'])->default('belum bayar');

             // Harga total
            $table->decimal('total_harga', 15, 2)->default(0);
            $table->decimal('total_harga_after_tax', 15, 2)->default(0);

            // Jumlah produk & point reward
            $table->unsignedInteger('jumlah_program')->default(0);
            $table->unsignedInteger('reward_point')->default(0);


            // ===== STATUS UTAMA =====
            $table->enum('status_pengajuan', ['pending','approved','rejected'])
                ->default('pending')
                ->comment('Status Pengajuan Order');

            $table->enum('status_product', ['pending','ready_stock','sold_out', 'rejected'])
                ->default('pending')
                ->comment('Status Ketersediaan Produk');

            $table->enum('status_order', [
                'pending','confirmed','processing','on_hold',
                'delivered','completed','cancelled','rejected'
            ])->default('pending')
              ->comment('Status Proses Order');

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
            
            // File order
            $table->string('order_file')->nullable();
            $table->string('order_excel')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
