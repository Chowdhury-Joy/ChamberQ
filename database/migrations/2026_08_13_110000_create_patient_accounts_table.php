<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('phone', 15)->unique();
            $table->string('name')->nullable();
            $table->string('nid', 13)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('nid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_accounts');
    }
};
