<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\Chamber;
use App\Models\Doctor;
use App\Models\Tenant;
use App\Models\WebPage;
use Throwable;

/**
 * Super Admin “ready for Google” list. Does not block save — empty chambers
 * still launch; this is the setup punch-list.
 *
 * @phpstan-type Item array{key: string, label: string, ok: bool, hint: string}
 */
final class SiteLaunchChecklist
{
    /**
     * @return list<Item>
     */
    public static function items(Tenant $tenant): array
    {
        $initialized = false;

        try {
            if (! tenancy()->initialized || tenant('id') !== $tenant->id) {
                tenancy()->initialize($tenant);
                $initialized = true;
            }

            return self::itemsWhileInitialized($tenant);
        } catch (Throwable) {
            return [[
                'key' => 'error',
                'label' => __('Could not read this clinic’s website'),
                'ok' => false,
                'hint' => __('Open the public site once, then refresh this page.'),
            ]];
        } finally {
            if ($initialized) {
                tenancy()->end();
            }
        }
    }

    public static function readyCount(Tenant $tenant): int
    {
        return count(array_filter(self::items($tenant), fn (array $item): bool => $item['ok']));
    }

    /**
     * @return list<Item>
     */
    private static function itemsWhileInitialized(Tenant $tenant): array
    {
        $home = WebPage::query()->where('slug', '/')->where('is_published', true)->first();
        $hero = $home ? PublicSeo::plain((string) self::firstHeroHeadline($home)) : '';
        $topics = PublicConditionTopics::all();
        $city = PublicSeo::practiceCity($tenant);
        $isClinic = ! $tenant->isSoloDoctor();
        $blogCount = $isClinic ? BlogPost::published()->count() : 0;

        $items = [
            [
                'key' => 'front_door',
                'label' => __('Website is on (Front door)'),
                'ok' => $tenant->hasFrontDoor(),
                'hint' => __('Turn on Front door or Google has nothing to index.'),
            ],
            [
                'key' => 'address',
                'label' => __('Chamber address includes a city'),
                'ok' => $city !== '',
                'hint' => __('Write the chamber address with the city, e.g. Panchlaish, Chattogram.'),
            ],
            [
                'key' => 'tagline',
                'label' => __('Tagline written'),
                'ok' => filled($tenant->tagline),
                'hint' => __('Branding Settings — one sentence patients would type into Google.'),
            ],
            [
                'key' => 'doctor',
                'label' => __('At least one doctor'),
                'ok' => Doctor::query()->exists(),
                'hint' => __('Search titles use the doctor’s specialty.'),
            ],
            [
                'key' => 'homepage',
                'label' => __('Homepage published'),
                'ok' => $home !== null,
                'hint' => __('Web Pages: publish the home page (/).'),
            ],
            [
                'key' => 'copy',
                'label' => __('Homepage has real words (not a blank Welcome)'),
                'ok' => self::hasRealCopy($tenant, $hero),
                'hint' => __('Hero headline or tagline must be this clinic’s words, not an empty Welcome.'),
            ],
            [
                'key' => 'topics',
                'label' => __('Condition topic pages (from the homepage list)'),
                'ok' => $topics !== [],
                'hint' => __('Add named conditions on the homepage library. Each one gets its own /conditions/… page.'),
            ],
        ];

        if ($isClinic) {
            $items[] = [
                'key' => 'blog',
                'label' => __('At least one published blog article'),
                'ok' => $blogCount > 0,
                'hint' => __('Clinic SEO articles live under Blog. One live article is the launch minimum.'),
            ];
        }

        return $items;
    }

    private static function hasRealCopy(Tenant $tenant, string $hero): bool
    {
        if (filled($tenant->tagline) && PublicSeo::plain((string) $tenant->tagline) !== '') {
            return true;
        }

        $hero = trim($hero);
        if ($hero === '') {
            return false;
        }

        $generic = ['home', 'welcome', 'welcome to our chamber', 'welcome to our clinic'];

        return ! in_array(mb_strtolower($hero), $generic, true);
    }

    private static function firstHeroHeadline(WebPage $page): string
    {
        foreach ((array) $page->content as $block) {
            if (! is_array($block) || ($block['type'] ?? '') !== 'hero') {
                continue;
            }
            if (! empty($block['data']['is_hidden'])) {
                continue;
            }

            return (string) ($block['data']['headline'] ?? '');
        }

        return '';
    }
}
