<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Nullable: optional on booking. Unique per tenant when set so one
            // NID cannot point at two people in the same chamber.
            $table->string('nid', 13)->nullable()->after('phone');
            $table->unique(['tenant_id', 'nid'], 'patients_tenant_nid_unique');
            $table->index('nid', 'patients_nid_index');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_tenant_nid_unique');
            $table->dropIndex('patients_nid_index');
            $table->dropColumn('nid');
        });
    }
};
