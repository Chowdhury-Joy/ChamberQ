<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->json('practice_rules')->nullable()->after('collect_fee_at_checkin');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->json('practice_rules')->nullable()->after('collect_fee_at_checkin');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('practice_rules');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('practice_rules');
        });
    }
};
