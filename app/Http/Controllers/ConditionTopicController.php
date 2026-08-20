<?php

namespace App\Http\Controllers;

use App\Support\PublicConditionTopics;
use App\Support\PublicSeo;
use Illuminate\View\View;

class ConditionTopicController extends Controller
{
    public function index(): View
    {
        $this->ensureFrontDoor();

        $topics = PublicConditionTopics::all();
        abort_if($topics === [], 404);

        $tenant = tenant();
        $title = __('Conditions we treat');

        return view($this->viewName('index'), [
            'topics' => $topics,
            ...$this->pageData($tenant, $title, [
                'description' => PublicSeo::practiceDescription($tenant),
            ]),
        ]);
    }

    public function show(string $slug): View
    {
        $this->ensureFrontDoor();

        $topic = PublicConditionTopics::find($slug);
        abort_if($topic === null, 404);

        $tenant = tenant();
        $description = $topic['description'] !== ''
            ? $topic['description']
            : __('Book a serial with :name for :condition.', [
                'name' => $tenant->displayName(),
                'condition' => $topic['name'],
            ]);

        return view($this->viewName('show'), [
            'topic' => $topic,
            ...$this->pageData($tenant, $topic['name'], [
                'description' => $description,
                'jsonLd' => [
                    PublicSeo::practiceGraph($tenant),
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'MedicalCondition',
                        'name' => $topic['name'],
                        'description' => $description,
                    ],
                ],
            ]),
        ]);
    }

    private function ensureFrontDoor(): void
    {
        abort_unless(tenant()?->hasFrontDoor(), 404);
    }

    private function viewName(string $page): string
    {
        $folder = tenant()?->isSoloDoctor() ? 'solo' : 'clinic';

        return 'tenant.'.$folder.'.conditions.'.$page;
    }

    /**
     * @param  array{description?: ?string, jsonLd?: list<array<string, mixed>>}  $seo
     * @return array<string, mixed>
     */
    private function pageData(mixed $tenant, string $pageTitle, array $seo = []): array
    {
        $brand = $tenant?->displayName() ?? 'ChamberQ';

        return [
            'pageTitle' => $pageTitle,
            'tenant' => $tenant,
            'brand' => $brand,
            'themeColor' => $tenant?->cssThemeColor() ?? \App\Models\Tenant::DEFAULT_THEME_COLOR,
            'locale' => app()->getLocale(),
            'banglaHomepage' => $tenant?->hasFeature('bangla_homepage') ?? false,
            'seo' => $tenant
                ? PublicSeo::tenantPage(
                    $tenant,
                    PublicSeo::labeledTitle($tenant, $pageTitle),
                    $seo['description'] ?? null,
                    true,
                    null,
                    'website',
                    $seo['jsonLd'] ?? [],
                )
                : [],
        ];
    }
}
