<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('offer_prescription_lifetime_free')->default(false)->after('setup_paid_at');
            $table->boolean('offer_prepaid_year_setup')->default(false)->after('offer_prescription_lifetime_free');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'offer_prescription_lifetime_free',
                'offer_prepaid_year_setup',
            ]);
        });
    }
};
