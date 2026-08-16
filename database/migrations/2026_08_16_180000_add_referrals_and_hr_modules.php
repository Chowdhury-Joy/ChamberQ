<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referring_doctors', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('specialty')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active', 'name']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('referring_doctor_id')->nullable()->after('procedure_status');

            $table->foreign('referring_doctor_id')->references('id')->on('referring_doctors')->nullOnDelete();
        });

        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('referring_doctor_id');
            $table->uuid('booking_id');
            $table->uuid('income_cash_entry_id')->nullable();
            $table->string('kind');
            $table->unsignedInteger('amount_taka');
            $table->string('status')->default('pending');
            $table->date('occurred_on');
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->uuid('payout_cash_entry_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('referring_doctor_id')->references('id')->on('referring_doctors')->cascadeOnDelete();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreign('income_cash_entry_id')->references('id')->on('chamber_cash_entries')->nullOnDelete();
            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('payout_cash_entry_id')->references('id')->on('chamber_cash_entries')->nullOnDelete();
            $table->unique(['tenant_id', 'booking_id'], 'referral_commission_booking_unique');
            $table->index(['tenant_id', 'referring_doctor_id', 'status', 'occurred_on'], 'referral_comm_doctor_status_idx');
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('job_title')->nullable();
            $table->unsignedInteger('monthly_salary_taka')->default(0);
            $table->date('joined_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'is_active', 'name']);
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('work_date');
            $table->string('status')->default('present');
            $table->time('check_in_at')->nullable();
            $table->time('check_out_at')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'employee_id', 'work_date'], 'attendance_employee_date_unique');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('leave_type');
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('review_note')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['tenant_id', 'status', 'start_date']);
        });

        Schema::create('payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('pay_period', 7);
            $table->unsignedInteger('amount_taka');
            $table->date('paid_on');
            $table->string('method')->default('cash');
            $table->uuid('cash_entry_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('cash_entry_id')->references('id')->on('chamber_cash_entries')->nullOnDelete();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['tenant_id', 'employee_id', 'pay_period'], 'payroll_employee_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('referral_commissions');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['referring_doctor_id']);
            $table->dropColumn('referring_doctor_id');
        });

        Schema::dropIfExists('referring_doctors');
    }
};
