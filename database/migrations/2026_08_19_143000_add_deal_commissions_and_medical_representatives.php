<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_representatives', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('medical_representative_id')->nullable()->after('marketer_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('paying_setup_amount')->nullable()->after('monthly_amount_due');
            $table->unsignedInteger('paying_monthly_amount')->nullable()->after('paying_setup_amount');
            $table->decimal('commission_setup_mr_rate', 5, 4)->nullable()->after('offer_prepaid_year_setup');
            $table->decimal('commission_setup_marketer_rate', 5, 4)->nullable()->after('commission_setup_mr_rate');
            $table->decimal('commission_year1_prepaid_mr_rate', 5, 4)->nullable()->after('commission_setup_marketer_rate');
            $table->decimal('commission_year1_prepaid_marketer_rate', 5, 4)->nullable()->after('commission_year1_prepaid_mr_rate');
            $table->decimal('commission_year2_mr_rate', 5, 4)->nullable()->after('commission_year1_prepaid_marketer_rate');
            $table->decimal('commission_year2_marketer_rate', 5, 4)->nullable()->after('commission_year2_mr_rate');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['marketer_id']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropUnique(['marketer_id', 'tenant_id', 'type', 'period']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('marketer_id')->nullable()->change();
            $table->foreignId('medical_representative_id')->nullable()->after('marketer_id')->constrained()->nullOnDelete();
            $table->string('payee_key', 40)->nullable()->after('medical_representative_id');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
        });

        foreach (DB::table('commissions')->whereNull('payee_key')->get() as $row) {
            DB::table('commissions')->where('id', $row->id)->update([
                'payee_key' => 'marketer:'.$row->marketer_id,
            ]);
        }

        Schema::table('commissions', function (Blueprint $table) {
            $table->string('payee_key', 40)->nullable(false)->change();
            $table->unique(['payee_key', 'tenant_id', 'type', 'period']);
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->dropUnique(['payee_key', 'tenant_id', 'type', 'period']);
            $table->dropConstrainedForeignId('medical_representative_id');
            $table->dropColumn('payee_key');
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropForeign(['marketer_id']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('marketer_id')->nullable(false)->change();
            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->unique(['marketer_id', 'tenant_id', 'type', 'period']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('medical_representative_id');
            $table->dropColumn([
                'paying_setup_amount',
                'paying_monthly_amount',
                'commission_setup_mr_rate',
                'commission_setup_marketer_rate',
                'commission_year1_prepaid_mr_rate',
                'commission_year1_prepaid_marketer_rate',
                'commission_year2_mr_rate',
                'commission_year2_marketer_rate',
            ]);
        });

        Schema::dropIfExists('medical_representatives');
    }
};
