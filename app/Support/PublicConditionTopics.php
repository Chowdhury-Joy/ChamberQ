<?php

namespace App\Support;

use App\Models\WebPage;
use Illuminate\Support\Str;

/**
 * Public topic pages from the homepage condition library — not the clinical
 * conditions catalogue. Empty names are skipped; duplicate names get -2, -3 slugs.
 */
final class PublicConditionTopics
{
    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     description: string,
     *     features: list<string>
     * }>
     */
    public static function all(): array
    {
        $page = WebPage::query()
            ->where('slug', '/')
            ->where('is_published', true)
            ->first();

        if ($page === null) {
            return [];
        }

        $topics = [];
        $used = [];

        foreach ((array) $page->content as $block) {
            if (! is_array($block) || ($block['type'] ?? '') !== 'condition_library') {
                continue;
            }
            if (! empty($block['data']['is_hidden'])) {
                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            foreach ((array) ($data['conditions'] ?? []) as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $base = Str::slug($name);
                if ($base === '') {
                    $base = 'topic';
                }

                $slug = $base;
                $n = 2;
                while (isset($used[$slug])) {
                    $slug = $base.'-'.$n;
                    $n++;
                }
                $used[$slug] = true;

                $topics[] = [
                    'slug' => $slug,
                    'name' => $name,
                    'description' => PublicSeo::plain((string) ($row['description'] ?? '')),
                    'features' => self::featureLabels($row['features'] ?? []),
                ];
            }
        }

        return $topics;
    }

    /**
     * @return array{slug: string, name: string, description: string, features: list<string>}|null
     */
    public static function find(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        foreach (self::all() as $topic) {
            if ($topic['slug'] === $slug) {
                return $topic;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $features
     * @return list<string>
     */
    private static function featureLabels(mixed $features): array
    {
        $labels = [];

        foreach ((array) $features as $feature) {
            $label = is_array($feature)
                ? (string) ($feature['label'] ?? $feature['name'] ?? reset($feature) ?: '')
                : (string) $feature;
            $label = trim($label);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels;
    }
}
