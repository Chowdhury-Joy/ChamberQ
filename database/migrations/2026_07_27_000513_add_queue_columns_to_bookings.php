<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('called_at')->nullable();
            $table->timestamp('in_chamber_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('skip_count')->default(0);
            $table->unsignedInteger('retry_queue_position')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'called_at',
                'in_chamber_at',
                'completed_at',
                'skip_count',
                'retry_queue_position',
            ]);
        });
    }
};
