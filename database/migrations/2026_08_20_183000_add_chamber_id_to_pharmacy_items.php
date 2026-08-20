<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pharmacy_items', function (Blueprint $table) {
            $table->foreignId('chamber_id')->nullable()->after('tenant_id')->constrained('chambers')->restrictOnDelete();
            $table->index(['tenant_id', 'chamber_id']);
        });

        Schema::table('pharmacy_counts', function (Blueprint $table) {
            $table->foreignId('chamber_id')->nullable()->after('tenant_id')->constrained('chambers')->nullOnDelete();
            $table->index(['tenant_id', 'chamber_id', 'status']);
        });

        $firstChamberByTenant = DB::table('chambers')
            ->select('tenant_id', DB::raw('MIN(id) as id'))
            ->groupBy('tenant_id')
            ->get();

        foreach ($firstChamberByTenant as $row) {
            DB::table('pharmacy_items')
                ->where('tenant_id', $row->tenant_id)
                ->whereNull('chamber_id')
                ->update(['chamber_id' => $row->id]);
        }
    }

    public function down(): void
    {
        Schema::table('pharmacy_counts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chamber_id');
        });

        Schema::table('pharmacy_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chamber_id');
        });
    }
};
