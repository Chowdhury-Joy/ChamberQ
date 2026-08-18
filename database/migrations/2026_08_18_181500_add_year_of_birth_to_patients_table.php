<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->unsignedSmallInteger('year_of_birth')->nullable()->after('date_of_birth');
        });

        foreach (DB::table('patients')->orderBy('id')->cursor() as $row) {
            $year = null;

            if (filled($row->date_of_birth)) {
                $year = (int) Carbon::parse($row->date_of_birth)->year;
            } elseif ($row->age !== null) {
                $recorded = filled($row->age_recorded_at)
                    ? Carbon::parse($row->age_recorded_at)
                    : Carbon::today();
                $year = (int) $recorded->year - (int) $row->age;
            }

            if ($year === null) {
                continue;
            }

            $min = (int) now()->year - 120;
            $max = (int) now()->year;
            if ($year < $min || $year > $max) {
                continue;
            }

            DB::table('patients')->where('id', $row->id)->update(['year_of_birth' => $year]);
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('year_of_birth');
        });
    }
};
