@php
    $product = config('marketing.product_name');
    $whatsapp = preg_replace('/\D+/', '', (string) config('marketing.whatsapp'));
    $solo = config('marketing.plans.solo');
    $clinic = config('marketing.plans.clinic');

    $wa = function (string $message) use ($whatsapp): string {
        return 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message);
    };

    $taka = function (int $amount): string {
        return '৳'.number_format($amount);
    };

    $soloWa = $wa('Hi — I\'m a solo doctor interested in Doctor Gemini (Solo plan).');
    $clinicWa = $wa('Hi — I\'m interested in Doctor Gemini for our clinic (Clinic plan).');
    $generalWa = $wa('Hi — I\'m a solo doctor and want to know how Doctor Gemini can help my chamber.');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="#ffffff">
    <meta name="description" content="Doctor Gemini helps solo doctors in Bangladesh cut waiting time so patients leave happier and recommend your chamber.">
    <title>{{ $product }} — Patients wait less. They tell others.</title>
    <link rel="stylesheet" href="{{ asset('css/marketing.css') }}">
</head>
<body class="mk">
    @include('marketing.partials.nav')
    @include('marketing.partials.hero')
    @include('marketing.partials.before-after')
    @include('marketing.partials.steps')
    @include('marketing.partials.value')
    @include('marketing.partials.pricing')
    @include('marketing.partials.faq')
    @include('marketing.partials.footer')
    @include('marketing.partials.sticky-cta')

    <script>
        (function () {
            var sticky = document.querySelector('[data-mk-sticky]');
            var hero = document.querySelector('[data-mk-hero]');
            if (!sticky || !hero || !('IntersectionObserver' in window)) {
                return;
            }
            var stickyObs = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    var show = !entry.isIntersecting;
                    sticky.classList.toggle('is-visible', show);
                    sticky.setAttribute('aria-hidden', show ? 'false' : 'true');
                });
            }, { threshold: 0 });
            stickyObs.observe(hero);
        })();
    </script>
</body>
</html>
