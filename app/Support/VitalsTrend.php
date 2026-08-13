<?php

namespace App\Support;

use App\Models\VisitRecord;
use Illuminate\Support\Collection;

/**
 * Build simple weight / BP trend series from past visit records.
 */
class VitalsTrend
{
    /**
     * @param  Collection<int, VisitRecord>  $records  Oldest first
     * @return array{
     *     weight: list<array{label: string, value: float}>,
     *     systolic: list<array{label: string, value: int}>,
     *     diastolic: list<array{label: string, value: int}>,
     * }
     */
    public static function fromVisitRecords(Collection $records, int $limit = 12): array
    {
        $weight = [];
        $systolic = [];
        $diastolic = [];

        foreach ($records->take(-$limit) as $record) {
            $label = $record->recorded_at?->format('j M')
                ?? $record->booking?->booking_date?->format('j M')
                ?? '';

            if ($label === '') {
                continue;
            }

            if ($record->weight_kg !== null && $record->weight_kg > 0) {
                $weight[] = ['label' => $label, 'value' => (float) $record->weight_kg];
            }

            if ($record->bp_systolic !== null && $record->bp_diastolic !== null) {
                $systolic[] = ['label' => $label, 'value' => (int) $record->bp_systolic];
                $diastolic[] = ['label' => $label, 'value' => (int) $record->bp_diastolic];
            }
        }

        return [
            'weight' => $weight,
            'systolic' => $systolic,
            'diastolic' => $diastolic,
        ];
    }

    /**
     * @param  list<array{label: string, value: float|int}>  $points
     * @return array{path: string, labels: list<string>, min: float, max: float}|null
     */
    public static function lineChart(array $points, int $width = 220, int $height = 72, int $padding = 8): ?array
    {
        if (count($points) < 2) {
            return null;
        }

        $values = array_map(fn (array $point): float => (float) $point['value'], $points);
        $min = min($values);
        $max = max($values);

        if ($min === $max) {
            $min -= 1;
            $max += 1;
        }

        $innerWidth = $width - ($padding * 2);
        $innerHeight = $height - ($padding * 2);
        $count = count($points);
        $coords = [];

        foreach ($points as $index => $point) {
            $x = $padding + ($count === 1 ? 0 : ($index / ($count - 1)) * $innerWidth);
            $ratio = ((float) $point['value'] - $min) / ($max - $min);
            $y = $padding + $innerHeight - ($ratio * $innerHeight);
            $coords[] = round($x, 1).','.round($y, 1);
        }

        return [
            'path' => 'M '.implode(' L ', $coords),
            'labels' => array_map(fn (array $point): string => $point['label'], $points),
            'min' => $min,
            'max' => $max,
            'width' => $width,
            'height' => $height,
        ];
    }
}
