<?php

namespace App\Models\Relations;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * HasMany that also matches parent `session_date` to related `booking_date`.
 *
 * A plain `whereDate('booking_date', $this->session_date)` bakes one parent's
 * date into the relation definition, so eager-loading multiple live sessions
 * for different dates attaches the wrong (or empty) bookings. This relation
 * constrains correctly for both lazy and eager loads.
 */
class HasManyByScheduleAndDate extends HasMany
{
    public function addConstraints(): void
    {
        parent::addConstraints();

        if (static::$constraints && $this->parent->getAttribute('session_date') !== null) {
            $this->query->where(
                $this->related->getTable().'.booking_date',
                $this->dateString($this->parent->session_date)
            );
        }
    }

    public function addEagerConstraints(array $models): void
    {
        parent::addEagerConstraints($models);

        $dates = collect($models)
            ->map(fn ($model) => $this->dateString($model->getAttribute('session_date')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($dates !== []) {
            $column = $this->related->qualifyColumn('booking_date');

            $this->query->whereIn($column, $dates);
        }
    }

    public function match(array $models, Collection $results, $relation): array
    {
        $dictionary = [];

        foreach ($results as $result) {
            $foreign = $result->getAttribute($this->getForeignKeyName());
            $date = $this->dateString($result->getAttribute('booking_date'));
            if ($foreign === null || $date === null) {
                continue;
            }
            $dictionary[$foreign][$date][] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $date = $this->dateString($model->getAttribute('session_date'));
            $records = ($key !== null && $date !== null)
                ? ($dictionary[$key][$date] ?? [])
                : [];

            $model->setRelation($relation, $this->related->newCollection($records));
        }

        return $models;
    }

    private function dateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
