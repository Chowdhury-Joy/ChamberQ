<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Laravel's built-in 'date' cast reads back a start-of-day Carbon instance,
 * but writes through the model's generic `getDateFormat()` — 'Y-m-d H:i:s' —
 * so a genuinely date-only column (booking_date, session_date, slot_blocks.date)
 * still gets a trailing "00:00:00" on every write. Real DATE columns
 * (MySQL/Postgres) coerce that away silently, but SQLite has no such type and
 * stores it literally, so a query hot path can never compare it as a plain
 * string equality — only `whereDate()`, which wraps the column in a SQL
 * function and defeats an index built on it. This cast makes storage
 * genuinely 'Y-m-d' on every driver, so a plain `where('col', $ymd)` matches
 * everywhere and stays index-friendly on the columns it is actually built for.
 */
class DateOnly implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value)->startOfDay();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toDateString();
    }
}
