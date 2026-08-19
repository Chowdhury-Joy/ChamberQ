<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('phone', 15);
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'phone', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_otp_codes');
    }
};
