<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('display_name');
            $table->string('phone')->nullable();
            $table->string('payout_account')->nullable();
            $table->decimal('setup_commission_rate', 5, 4)->default(0.20);
            $table->decimal('monthly_commission_rate', 5, 4)->default(0.10);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label')->nullable();
            $table->unsignedTinyInteger('setup_percent')->nullable();
            $table->unsignedTinyInteger('monthly_percent')->nullable();
            $table->foreignId('marketer_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemption_count')->default(0);
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('marketer_id')->nullable()->after('plan_tier')->constrained()->nullOnDelete();
            $table->foreignId('discount_code_id')->nullable()->after('marketer_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('list_setup_amount')->nullable()->after('discount_code_id');
            $table->unsignedInteger('list_monthly_amount')->nullable()->after('list_setup_amount');
            $table->unsignedInteger('setup_amount_due')->nullable()->after('list_monthly_amount');
            $table->unsignedInteger('monthly_amount_due')->nullable()->after('setup_amount_due');
            $table->text('referral_note')->nullable()->after('monthly_amount_due');
            $table->timestamp('referred_at')->nullable()->after('referral_note');
            $table->timestamp('setup_paid_at')->nullable()->after('referred_at');
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('type');
            $table->string('period', 7)->nullable();
            $table->unsignedInteger('list_amount');
            $table->unsignedInteger('discount_amount')->default(0);
            $table->unsignedInteger('amount_paid');
            $table->foreignId('discount_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'type', 'period']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketer_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('billing_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('period', 7)->nullable();
            $table->unsignedInteger('base_amount');
            $table->decimal('rate', 5, 4);
            $table->unsignedInteger('commission_amount');
            $table->string('status')->default('pending_doctor_payment');
            $table->timestamp('paid_at')->nullable();
            $table->text('payout_note')->nullable();
            $table->timestamps();

            $table->unique(['marketer_id', 'tenant_id', 'type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('billing_payments');
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketer_id');
            $table->dropConstrainedForeignId('discount_code_id');
            $table->dropColumn([
                'list_setup_amount',
                'list_monthly_amount',
                'setup_amount_due',
                'monthly_amount_due',
                'referral_note',
                'referred_at',
                'setup_paid_at',
            ]);
        });
        Schema::dropIfExists('discount_codes');
        Schema::dropIfExists('marketers');
    }
};
