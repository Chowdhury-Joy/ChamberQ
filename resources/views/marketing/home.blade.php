@php
    $product = config('marketing.product_name');
    $whatsapp = preg_replace('/\D+/', '', (string) config('marketing.whatsapp'));
    $solo = config('marketing.plans.solo');
    $clinic = config('marketing.plans.clinic');

    $refCode = session('referral.code');
    $discountCode = session('referral.discount_code');
    $refSuffix = '';
    if ($refCode) {
        $refSuffix .= ' Ref: '.$refCode.'.';
    }
    if ($discountCode) {
        $refSuffix .= ' Code: '.$discountCode.'.';
    }

    $wa = function (string $message) use ($whatsapp): string {
        return 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($message);
    };

    $taka = function (int $amount): string {
        return '৳'.number_format($amount);
    };

    $soloWa = $wa('Hi — I\'m a solo doctor interested in ChamberQ (Maestro — full package).'.$refSuffix);
    $clinicWa = $wa('Hi — I\'m interested in ChamberQ for our clinic (Clinic plan).'.$refSuffix);
    $generalWa = $wa('Hi — I\'m a solo doctor and want to know how ChamberQ can help my chamber.'.$refSuffix);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light only">
    <meta name="theme-color" content="#0c3a3b">
    <meta name="description" content="ChamberQ for solo doctors: clearer serials, a live queue you control, and fewer interruptions in the consult. We set it up with you.">
    <title>{{ $product }} — For your chamber practice</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/marketing.css') }}">
    <link rel="stylesheet" href="{{ asset('css/card-grid.css') }}">
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
