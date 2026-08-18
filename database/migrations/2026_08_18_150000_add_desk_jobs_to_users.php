<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('desk_jobs')->nullable()->after('assigned_doctor_id');
            $table->boolean('desk_is_lead')->default(false)->after('desk_jobs');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['desk_jobs', 'desk_is_lead']);
        });
    }
};
