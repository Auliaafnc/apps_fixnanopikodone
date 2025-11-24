<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Role (opsional — jika kamu pakai spatie role, ini bisa tetap berguna untuk default/fallback)
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->nullable()->after('password')->index();
            }

            // FK -> employees
            if (!Schema::hasColumn('users', 'employee_id')) {
                $table->foreignId('employee_id')
                    ->nullable()
                    ->after('role')
                    ->constrained('employees')
                    ->nullOnDelete();
            }

            // FK -> departments
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('employee_id')
                    ->constrained('departments')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropConstrainedForeignId('department_id');
            }
            if (Schema::hasColumn('users', 'employee_id')) {
                $table->dropConstrainedForeignId('employee_id');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
