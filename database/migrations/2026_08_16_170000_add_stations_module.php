<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->string('kind')->nullable()->after('session_name');
        });

        Schema::create('fee_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('label');
            $table->unsignedInteger('list_price_taka');
            $table->unsignedInteger('house_share_taka')->default(0);
            $table->string('sitting_kind')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });

        Schema::table('chamber_cash_entries', function (Blueprint $table) {
            $table->unsignedInteger('list_price_taka')->nullable()->after('amount');
            $table->unsignedInteger('cash_taka')->nullable()->after('list_price_taka');
            $table->unsignedInteger('mobile_taka')->nullable()->after('cash_taka');
            $table->string('mobile_method')->nullable()->after('mobile_taka');
            $table->unsignedInteger('discount_taka')->nullable()->after('mobile_method');
            $table->unsignedInteger('clinic_share_taka')->nullable()->after('discount_taka');
            $table->unsignedInteger('doctor_share_taka')->nullable()->after('clinic_share_taka');
            $table->unsignedBigInteger('fee_catalog_item_id')->nullable()->after('doctor_share_taka');

            $table->foreign('fee_catalog_item_id')->references('id')->on('fee_catalog_items')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedInteger('voucher_number')->nullable()->after('serial_number');
            $table->uuid('related_booking_id')->nullable()->after('voucher_number');
            $table->string('procedure_status')->nullable()->after('related_booking_id');

            $table->foreign('related_booking_id')->references('id')->on('bookings')->nullOnDelete();
            $table->index(['tenant_id', 'booking_date', 'voucher_number']);
        });

        Schema::create('schedule_session_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('schedule_session_id');
            $table->date('override_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('slot_cap')->nullable();
            $table->unsignedInteger('walk_in_overflow_cap')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('schedule_session_id')->references('id')->on('schedule_sessions')->cascadeOnDelete();
            $table->unique(['tenant_id', 'schedule_session_id', 'override_date'], 'session_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_session_overrides');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['related_booking_id']);
            $table->dropIndex(['tenant_id', 'booking_date', 'voucher_number']);
            $table->dropColumn(['voucher_number', 'related_booking_id', 'procedure_status']);
        });

        Schema::table('chamber_cash_entries', function (Blueprint $table) {
            $table->dropForeign(['fee_catalog_item_id']);
            $table->dropColumn([
                'list_price_taka',
                'cash_taka',
                'mobile_taka',
                'mobile_method',
                'discount_taka',
                'clinic_share_taka',
                'doctor_share_taka',
                'fee_catalog_item_id',
            ]);
        });

        Schema::dropIfExists('fee_catalog_items');

        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
