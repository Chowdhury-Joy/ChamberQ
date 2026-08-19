<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('fee_catalog_item_id')->nullable()->after('referring_doctor_id');
            $table->foreign('fee_catalog_item_id')->references('id')->on('fee_catalog_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['fee_catalog_item_id']);
            $table->dropColumn('fee_catalog_item_id');
        });
    }
};
