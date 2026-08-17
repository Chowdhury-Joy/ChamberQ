<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('cash_categories', function (Blueprint $table) {
      $table->id();
      $table->string('tenant_id');
      $table->string('type');
      $table->string('code');
      $table->string('name');
      $table->boolean('is_active')->default(true);
      $table->boolean('is_builtin')->default(false);
      $table->boolean('is_locked')->default(false);
      $table->unsignedSmallInteger('sort_order')->default(0);
      $table->timestamps();

      $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
      $table->unique(['tenant_id', 'code']);
      $table->index(['tenant_id', 'type', 'is_active']);
    });

    $now = now();

    $defaults = [
      ['type' => 'income', 'code' => 'patient', 'name' => 'Patient fee', 'is_active' => true, 'is_builtin' => true, 'is_locked' => true, 'sort_order' => 10],
      ['type' => 'income', 'code' => 'waived', 'name' => 'Waived', 'is_active' => true, 'is_builtin' => true, 'is_locked' => true, 'sort_order' => 20],
      ['type' => 'income', 'code' => 'other_income', 'name' => 'Other income', 'is_active' => true, 'is_builtin' => true, 'is_locked' => false, 'sort_order' => 30],
      ['type' => 'expense', 'code' => 'rent', 'name' => 'Rent', 'is_active' => true, 'is_builtin' => true, 'is_locked' => false, 'sort_order' => 10],
      ['type' => 'expense', 'code' => 'utilities', 'name' => 'Utilities', 'is_active' => true, 'is_builtin' => true, 'is_locked' => false, 'sort_order' => 20],
      ['type' => 'expense', 'code' => 'supplies', 'name' => 'Supplies', 'is_active' => true, 'is_builtin' => true, 'is_locked' => false, 'sort_order' => 30],
      ['type' => 'expense', 'code' => 'salary', 'name' => 'Salary', 'is_active' => true, 'is_builtin' => true, 'is_locked' => true, 'sort_order' => 40],
      ['type' => 'expense', 'code' => 'transport', 'name' => 'Transport', 'is_active' => true, 'is_builtin' => true, 'is_locked' => false, 'sort_order' => 50],
      ['type' => 'expense', 'code' => 'referral_payout', 'name' => 'Referral payout', 'is_active' => true, 'is_builtin' => true, 'is_locked' => true, 'sort_order' => 60],
      ['type' => 'expense', 'code' => 'other_expense', 'name' => 'Other expense', 'is_active' => true, 'is_builtin' => true, 'is_locked' => false, 'sort_order' => 70],
    ];

    $tenantIds = DB::table('tenants')->pluck('id');

    foreach ($tenantIds as $tenantId) {
      foreach ($defaults as $row) {
        DB::table('cash_categories')->insert([
          ...$row,
          'tenant_id' => $tenantId,
          'created_at' => $now,
          'updated_at' => $now,
        ]);
      }
    }
  }

  public function down(): void
  {
    Schema::dropIfExists('cash_categories');
  }
};
