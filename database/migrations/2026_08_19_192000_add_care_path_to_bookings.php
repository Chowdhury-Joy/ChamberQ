<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->string('care_path', 32)->nullable()->after('procedure_status');
            $table->string('care_branch', 32)->nullable()->after('care_path');
            $table->uuid('care_origin_id')->nullable()->after('care_branch');
            $table->foreign('care_origin_id')->references('id')->on('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['care_origin_id']);
            $table->dropColumn(['care_path', 'care_branch', 'care_origin_id']);
        });
    }
};
