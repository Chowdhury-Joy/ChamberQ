<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class MarketingController extends Controller
{
    public function home(): View
    {
        $whatsapp = $this->digitsOnly((string) config('marketing.whatsapp'));
        $phone = (string) config('marketing.phone', '');
        $refSuffix = $this->referralSuffix();

        $payload = [
            'product' => (string) config('marketing.product_name', 'ChamberQ'),
            'phone' => $phone,
            'heroImage' => $this->publicMarketingImage((string) config('marketing.hero_image', '')),
            'beforeAfter' => $this->beforeAfter((array) config('marketing.before_after', [])),
            'steps' => $this->imageItems((array) config('marketing.steps', [])),
            'valuePoints' => $this->imageItems((array) config('marketing.value_points', [])),
            'solo' => $this->plan((array) config('marketing.plans.solo', [])),
            'clinic' => $this->plan((array) config('marketing.plans.clinic', [])),
            'frontDoor' => $this->modulePrice((array) config('marketing.modules.front_door', []), 5000, 1000),
            'prescription' => $this->modulePrice((array) config('marketing.modules.prescription', []), 4500, 250),
            'liveQueue' => $this->modulePrice((array) config('marketing.modules.live_queue', []), 18000, 2000),
            'bundle' => $this->modulePrice((array) config('marketing.modules.bundle_all', []), 25000, 3000),
            'soloWa' => $this->whatsappUrl($whatsapp, 'Hi — I\'m a solo doctor interested in ChamberQ (Maestro — full package).'.$refSuffix),
            'clinicWa' => $this->whatsappUrl($whatsapp, 'Hi — I\'m interested in ChamberQ for our clinic (Clinic plan).'.$refSuffix),
            'generalWa' => $this->whatsappUrl($whatsapp, 'Hi — I\'m a solo doctor and want to know how ChamberQ can help my chamber.'.$refSuffix),
            'modulesWa' => $this->whatsappUrl($whatsapp, 'Hi — I\'m a solo doctor. I want to pick ChamberQ modules (website / prescription / queue).'.$refSuffix),
            'taka' => static fn (int $amount): string => '৳'.number_format($amount),
        ];

        return view('marketing.home', $payload);
    }

    private function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function referralSuffix(): string
    {
        $ref = strtolower((string) session('referral.code', ''));
        $discount = strtoupper((string) session('referral.discount_code', ''));
        $suffix = '';

        if (preg_match('/^[a-z0-9\-]{1,50}$/', $ref) === 1) {
            $suffix .= ' Ref: '.$ref.'.';
        }

        if (preg_match('/^[A-Z0-9\-]{1,50}$/', $discount) === 1) {
            $suffix .= ' Code: '.$discount.'.';
        }

        return $suffix;
    }

    private function whatsappUrl(string $digits, string $message): string
    {
        if ($digits === '') {
            return '#';
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{name: string, tagline: string, setup: int, monthly: int, features: list<string>}
     */
    private function plan(array $plan): array
    {
        $features = [];
        foreach ((array) ($plan['features'] ?? []) as $feature) {
            if (is_string($feature) && $feature !== '') {
                $features[] = $feature;
            }
        }

        return [
            'name' => (string) ($plan['name'] ?? ''),
            'tagline' => (string) ($plan['tagline'] ?? ''),
            'setup' => (int) ($plan['setup'] ?? 0),
            'monthly' => (int) ($plan['monthly'] ?? 0),
            'features' => $features,
        ];
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array{setup: int, monthly: int}
     */
    private function modulePrice(array $module, int $setupFallback, int $monthlyFallback): array
    {
        return [
            'setup' => (int) ($module['setup'] ?? $setupFallback),
            'monthly' => (int) ($module['monthly'] ?? $monthlyFallback),
        ];
    }

    /**
     * @param  array<string, mixed>  $copy
     * @return array{before: array{value: string, bullets: list<string>}, after: array{value: string, bullets: list<string>}}
     */
    private function beforeAfter(array $copy): array
    {
        return [
            'before' => $this->compareCard((array) ($copy['before'] ?? [])),
            'after' => $this->compareCard((array) ($copy['after'] ?? [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $card
     * @return array{value: string, bullets: list<string>}
     */
    private function compareCard(array $card): array
    {
        $bullets = [];
        foreach ((array) ($card['bullets'] ?? []) as $bullet) {
            if (is_string($bullet) && $bullet !== '') {
                $bullets[] = $bullet;
            }
        }

        return [
            'value' => (string) ($card['value'] ?? ''),
            'bullets' => $bullets,
        ];
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array{key: string, title: string, caption: string, image: ?string, featured: bool}>
     */
    private function imageItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $out[] = [
                'key' => (string) ($item['key'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'caption' => (string) ($item['caption'] ?? ''),
                'image' => $this->publicMarketingImage((string) ($item['image'] ?? '')),
                'featured' => ! empty($item['featured']),
            ];
        }

        return $out;
    }

    private function publicMarketingImage(string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        $relative = ltrim($relative, '/');

        if ($relative === '' || str_contains($relative, '..') || ! str_starts_with($relative, 'images/marketing/')) {
            return null;
        }

        if (! is_file(public_path($relative))) {
            return null;
        }

        return $relative;
    }
}
