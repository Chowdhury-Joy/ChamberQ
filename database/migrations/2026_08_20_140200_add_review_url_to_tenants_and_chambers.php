<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('review_url', 2048)->nullable()->after('whatsapp_number');
        });

        Schema::table('chambers', function (Blueprint $table) {
            $table->string('review_url', 2048)->nullable()->after('map_url');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('review_url');
        });

        Schema::table('chambers', function (Blueprint $table) {
            $table->dropColumn('review_url');
        });
    }
};
