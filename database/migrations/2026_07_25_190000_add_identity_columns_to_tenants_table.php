<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Without these the patient-facing brand was the raw subdomain slug,
            // so a clinic's own website displayed "demo" as its name.
            $table->string('name')->nullable()->after('id');
            $table->string('contact_phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('theme_color')->nullable();
            $table->string('default_locale', 5)->default('en');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'contact_phone', 'whatsapp_number', 'theme_color', 'default_locale',
            ]);
        });
    }
};
