<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('template_id')->nullable();
            $table->string('layout_id')->nullable();
            $table->text('custom_code')->nullable();
            $table->timestamp('custom_code_approved_at')->nullable();
            $table->string('billing_status')->default('active'); // active / read_only
            $table->string('plan_tier')->default('solo'); // solo / clinic
            $table->json('feature_flags')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
