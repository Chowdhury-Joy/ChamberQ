<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->string('practice_type', 32)->default('general_physician')->after('name');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->json('practice_types')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('practice_type');
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropColumn('practice_types');
        });
    }
};
