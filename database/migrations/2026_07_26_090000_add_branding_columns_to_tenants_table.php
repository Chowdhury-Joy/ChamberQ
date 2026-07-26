<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_url')->nullable()->after('theme_color');
            $table->string('favicon_url')->nullable()->after('logo_url');
            $table->string('font_family')->nullable()->default('Inter')->after('favicon_url');
            $table->string('tagline')->nullable()->after('font_family');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['logo_url', 'favicon_url', 'font_family', 'tagline']);
        });
    }
};
