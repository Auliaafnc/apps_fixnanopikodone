<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = [
        // Company & org
        'companies',
        'departments',
        'employees',

        // User mgmt
        'users',
        'roles',

        // Master data
        'brands',
        'categories',
        'products',
        'banners',

        // Token* (pakai yang memang ada di DB kamu)
        'tokens',
        'personal_access_tokens',

        // Customer area
        'customer_categories',
        'customer_programs',
        'customers',

        // Sales area
        'orders',
        'product_returns',
        'garansis',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Tambah kolom (tanpa index manual; FK akan bikin index sendiri)
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'created_by')) {
                    $t->unsignedBigInteger('created_by')->nullable()->after('id');
                }
                if (! Schema::hasColumn($table, 'updated_by')) {
                    $t->unsignedBigInteger('updated_by')->nullable()->after('created_by');
                }
            });

            // Tambah FK dengan NAMA eksplisit biar tidak bentrok
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'created_by')) {
                    // nama FK: {table}_created_by_fk
                    $t->foreign('created_by', "{$table}_created_by_fk")
                        ->references('id')->on('users')
                        ->nullOnDelete();
                }
                if (Schema::hasColumn($table, 'updated_by')) {
                    // nama FK: {table}_updated_by_fk
                    $t->foreign('updated_by', "{$table}_updated_by_fk")
                        ->references('id')->on('users')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                // Drop FK pakai nama yang tadi kita set
                // (abaikan error jika tidak ada – Laravel akan handle jika nama tidak ada)
                try { $t->dropForeign("{$table}_updated_by_fk"); } catch (\Throwable $e) {}
                try { $t->dropForeign("{$table}_created_by_fk"); } catch (\Throwable $e) {}

                if (Schema::hasColumn($table, 'updated_by')) {
                    $t->dropColumn('updated_by');
                }
                if (Schema::hasColumn($table, 'created_by')) {
                    $t->dropColumn('created_by');
                }
            });
        }
    }
};
