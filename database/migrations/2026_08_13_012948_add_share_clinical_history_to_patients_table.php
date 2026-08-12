<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->boolean('share_clinical_history')->default(true)->after('medicines');
            $table->index(['phone', 'share_clinical_history'], 'patients_phone_share_index');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_phone_share_index');
            $table->dropColumn('share_clinical_history');
        });
    }
};
