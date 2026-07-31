<?php

namespace App\Http\Middleware;

use App\Models\DiscountCode;
use App\Models\Marketer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferralParams
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->has('ref')) {
            $code = strtolower(trim((string) $request->query('ref')));
            $marketer = Marketer::query()
                ->whereRaw('LOWER(code) = ?', [$code])
                ->where('is_active', true)
                ->first();
            if ($marketer) {
                session(['referral.marketer_id' => $marketer->id, 'referral.code' => $code]);
            }
        }

        if ($request->has('code')) {
            $discountCode = strtoupper(trim((string) $request->query('code')));
            $discount = DiscountCode::query()->where('code', $discountCode)->first();
            if ($discount && $discount->isValidNow()) {
                session(['referral.discount_code_id' => $discount->id, 'referral.discount_code' => $discountCode]);
            }
        }

        return $next($request);
    }
}
