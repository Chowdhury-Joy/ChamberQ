<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedTinyInteger('pharmacy_doctor_percent')->default(0);
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->unsignedTinyInteger('pharmacy_doctor_percent')->nullable();
        });

        Schema::create('pharmacy_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignUuid('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->unsignedInteger('sell_price_taka');
            $table->unsignedInteger('company_share_taka')->default(0);
            $table->string('unit_label', 32)->default('unit');
            $table->unsignedInteger('qty_on_hand')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('pharmacy_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('pharmacy_item_id')->constrained('pharmacy_items')->cascadeOnDelete();
            $table->unsignedInteger('qty_received');
            $table->unsignedInteger('qty_sold')->default(0);
            $table->unsignedInteger('qty_returned')->default(0);
            $table->unsignedInteger('qty_on_hand');
            $table->unsignedInteger('company_share_taka');
            $table->unsignedInteger('paid_taka')->default(0);
            $table->boolean('returnable')->default(true);
            $table->text('note')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('received_on');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'pharmacy_item_id']);
        });

        Schema::create('pharmacy_stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('pharmacy_item_id')->constrained('pharmacy_items')->cascadeOnDelete();
            $table->foreignId('pharmacy_delivery_id')->nullable()->constrained('pharmacy_deliveries')->nullOnDelete();
            $table->string('kind', 32);
            $table->integer('qty_delta');
            $table->unsignedInteger('qty_before');
            $table->unsignedInteger('qty_after');
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('pharmacy_counts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('status', 24);
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('pharmacy_count_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('pharmacy_count_id')->constrained('pharmacy_counts')->cascadeOnDelete();
            $table->foreignId('pharmacy_item_id')->constrained('pharmacy_items')->cascadeOnDelete();
            $table->unsignedInteger('system_qty');
            $table->unsignedInteger('counted_qty');
            $table->integer('difference');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['pharmacy_count_id', 'pharmacy_item_id']);
        });

        Schema::create('pharmacy_sales', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignUuid('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->foreignUuid('cash_entry_id')->nullable()->constrained('chamber_cash_entries')->nullOnDelete();
            $table->foreignUuid('refund_cash_entry_id')->nullable()->constrained('chamber_cash_entries')->nullOnDelete();
            $table->string('patient_name')->nullable();
            $table->string('patient_phone', 32)->nullable();
            $table->string('method', 32);
            $table->unsignedInteger('amount')->default(0);
            $table->unsignedInteger('cash_taka')->nullable();
            $table->unsignedInteger('mobile_taka')->nullable();
            $table->string('mobile_method', 32)->nullable();
            $table->boolean('waived')->default(false);
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'occurred_on']);
        });

        Schema::create('pharmacy_sale_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('pharmacy_sale_id')->constrained('pharmacy_sales')->cascadeOnDelete();
            $table->foreignId('pharmacy_item_id')->constrained('pharmacy_items')->restrictOnDelete();
            $table->foreignId('pharmacy_delivery_id')->constrained('pharmacy_deliveries')->restrictOnDelete();
            $table->foreignUuid('prescription_item_id')->nullable()->constrained('prescription_items')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('sell_price_taka');
            $table->unsignedInteger('company_share_taka');
            $table->unsignedInteger('shop_cut_taka');
            $table->unsignedInteger('line_total_taka');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('pharmacy_supplier_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('kind', 24);
            $table->unsignedInteger('amount');
            $table->foreignUuid('cash_entry_id')->nullable()->constrained('chamber_cash_entries')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('occurred_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::create('pharmacy_doctor_commissions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('doctor_id')->constrained('doctors')->cascadeOnDelete();
            $table->foreignId('pharmacy_sale_id')->constrained('pharmacy_sales')->cascadeOnDelete();
            $table->foreignId('pharmacy_sale_item_id')->constrained('pharmacy_sale_items')->cascadeOnDelete();
            $table->unsignedInteger('shop_cut_taka');
            $table->unsignedTinyInteger('percent');
            $table->unsignedInteger('amount_taka');
            $table->string('status', 24);
            $table->date('occurred_on');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('payout_cash_entry_id')->nullable()->constrained('chamber_cash_entries')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique('pharmacy_sale_item_id');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pharmacy_doctor_commissions');
        Schema::dropIfExists('pharmacy_supplier_settlements');
        Schema::dropIfExists('pharmacy_sale_items');
        Schema::dropIfExists('pharmacy_sales');
        Schema::dropIfExists('pharmacy_count_items');
        Schema::dropIfExists('pharmacy_counts');
        Schema::dropIfExists('pharmacy_stock_adjustments');
        Schema::dropIfExists('pharmacy_deliveries');
        Schema::dropIfExists('pharmacy_items');

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('pharmacy_doctor_percent');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('pharmacy_doctor_percent');
        });
    }
};
