<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chamber staff paste a Google Maps share link; they do not look up coordinates.
 * Existing lat/lng pairs are folded into the same link format before the columns go.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chambers', function (Blueprint $table) {
            $table->string('map_url', 2048)->nullable()->after('address');
        });

        DB::table('chambers')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('id')
            ->each(function ($chamber) {
                $lat = trim((string) $chamber->latitude);
                $lng = trim((string) $chamber->longitude);

                if ($lat === '' || $lng === '' || ! is_numeric($lat) || ! is_numeric($lng)) {
                    return;
                }

                DB::table('chambers')
                    ->where('id', $chamber->id)
                    ->update([
                        'map_url' => 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng),
                    ]);
            });

        Schema::table('chambers', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::table('chambers', function (Blueprint $table) {
            $table->string('latitude')->nullable()->after('address');
            $table->string('longitude')->nullable()->after('latitude');
        });

        // Recover coordinates from links we generated above (?q=lat,lng).
        DB::table('chambers')
            ->whereNotNull('map_url')
            ->orderBy('id')
            ->each(function ($chamber) {
                $query = [];
                parse_str((string) parse_url((string) $chamber->map_url, PHP_URL_QUERY), $query);
                $pair = explode(',', (string) ($query['q'] ?? ''));

                if (count($pair) !== 2 || ! is_numeric(trim($pair[0])) || ! is_numeric(trim($pair[1]))) {
                    return;
                }

                DB::table('chambers')
                    ->where('id', $chamber->id)
                    ->update([
                        'latitude' => trim($pair[0]),
                        'longitude' => trim($pair[1]),
                    ]);
            });

        Schema::table('chambers', function (Blueprint $table) {
            $table->dropColumn('map_url');
        });
    }
};
