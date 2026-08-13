<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->string('offline_sync_id', 40)->nullable()->after('recorded_at');
            $table->unique(['tenant_id', 'offline_sync_id']);
        });
    }

    public function down(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'offline_sync_id']);
            $table->dropColumn('offline_sync_id');
        });
    }
};
