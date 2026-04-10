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
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();

            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 32)->nullable();

            $table->text('address')->nullable();
            $table->string('national_id')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->string('employment_type', 32)->default('full_time');
            $table->string('employment_status', 32)->default('active');

            $table->date('hire_date');
            $table->date('probation_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->text('termination_notes')->nullable();

            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->decimal('base_salary', 14, 2)->nullable();
            $table->date('salary_effective_date')->nullable();
            $table->string('pay_frequency', 24)->default('monthly');
            $table->string('salary_currency', 3)->default('ETB');

            $table->string('bank_name')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('payroll_tax_id')->nullable();

            $table->decimal('hours_per_day', 6, 2)->nullable();
            $table->decimal('days_per_week', 5, 2)->nullable();
            $table->decimal('hourly_rate', 14, 2)->nullable();
            $table->decimal('payroll_tax_amount', 14, 2)->nullable();
            $table->decimal('net_salary_after_tax', 14, 2)->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('document_type', 48)->default('other');
            $table->string('file_path');
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('job_positions');
        Schema::dropIfExists('departments');
    }
};
