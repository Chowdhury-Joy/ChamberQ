<?php

namespace App\Support\DataBackup;

use Illuminate\Support\Facades\Schema;

class BackupCsv
{
    /**
     * @return list<string>
     */
    public static function exportableColumns(string $table): array
    {
        $columns = Schema::getColumnListing($table);

        return array_values(array_diff($columns, BackupTableMap::EXCLUDED_COLUMNS));
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $header
     */
    public static function writeHeader($handle, array $header): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header);
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $header
     * @param  array<string, mixed>  $row
     */
    public static function writeRow($handle, array $header, array $row): void
    {
        $line = [];

        foreach ($header as $column) {
            $line[] = self::serializeValue($row[$column] ?? null);
        }

        fputcsv($handle, $line);
    }

    /**
     * @return array{header: list<string>, rows: list<array<string, string|null>>}
     */
    public static function readFile(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("Could not open CSV: {$path}");
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);

            return ['header' => [], 'rows' => []];
        }

        if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
            $firstLine = substr($firstLine, 3);
        }

        $header = str_getcsv($firstLine);

        if ($header === false || $header === [null] || $header === []) {
            fclose($handle);

            return ['header' => [], 'rows' => []];
        }

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($data === [null] || $data === []) {
                continue;
            }

            $assoc = [];

            foreach ($header as $index => $column) {
                $value = $data[$index] ?? null;
                $assoc[$column] = $value === '' ? null : $value;
            }

            $rows[] = $assoc;
        }

        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
    }

    public static function serializeValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    public static function deserializeRow(array $row, string $table): array
    {
        $jsonColumns = self::jsonColumns($table);
        $booleanColumns = self::booleanColumns($table);

        foreach ($row as $column => $value) {
            if ($value === null) {
                continue;
            }

            if (in_array($column, $jsonColumns, true)) {
                $decoded = json_decode($value, true);
                $row[$column] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;

                continue;
            }

            if (in_array($column, $booleanColumns, true)) {
                $row[$column] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $row;
    }

    /** @return list<string> */
    private static function jsonColumns(string $table): array
    {
        return match ($table) {
            'tenants' => ['feature_flags', 'data'],
            'conditions' => ['aliases'],
            'medicines' => ['aliases', 'practice_types'],
            'doctors' => ['notify_channels', 'practice_types', 'extra_fees'],
            'chambers' => ['hours'],
            'web_pages' => ['content'],
            default => [],
        };
    }

    /** @return list<string> */
    private static function booleanColumns(string $table): array
    {
        return match ($table) {
            'marketers' => ['is_active'],
            'discount_codes' => ['is_active'],
            default => [],
        };
    }
}
