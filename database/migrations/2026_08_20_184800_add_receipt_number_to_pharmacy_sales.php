<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_sales', function (Blueprint $table) {
            $table->unsignedInteger('receipt_number')->nullable()->after('note');
            $table->unique(
                ['tenant_id', 'receipt_number'],
                'pharmacy_sales_receipt_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('pharmacy_sales', function (Blueprint $table) {
            $table->dropUnique('pharmacy_sales_receipt_unique');
            $table->dropColumn('receipt_number');
        });
    }
};
