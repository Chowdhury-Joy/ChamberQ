<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deliberately numbered `0000_…` so it runs before every other migration,
 * including Laravel's own `0001_01_01_000000_create_users_table`.
 *
 * `tenants` is the root of the entire schema — `users` and nineteen other
 * tables declare a foreign key to it. Published by stancl/tenancy as
 * `2019_09_15_000010`, it sorted *after* the users table, so MySQL refused the
 * very first migration with "1824 Failed to open the referenced table
 * 'tenants'". SQLite never complained (it does not resolve foreign-key targets
 * at CREATE time), which is why this survived until the app was first pointed
 * at its production database. Do not renumber this above `0001`.
 */
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
