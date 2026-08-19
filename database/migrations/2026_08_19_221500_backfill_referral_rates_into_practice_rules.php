<?php

use App\Models\Tenant;
use App\Services\PracticeRules;
use Illuminate\Database\Migrations\Migration;

/**
 * Clinics that already had Referrals on were silently using ৳200 / ৳1,000
 * from PHP. Copy those amounts into practice_rules so live floors do not
 * drop to ৳0. New clinics keep ৳0 until Branding is filled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Tenant::query()->orderBy('id')->each(function (Tenant $tenant): void {
            if (! $tenant->hasReferrals()) {
                return;
            }

            $raw = is_array($tenant->practice_rules) ? $tenant->practice_rules : [];
            $flags = is_array($tenant->feature_flags) ? $tenant->feature_flags : [];
            $changed = false;

            $pairs = [
                'referral_visit_taka' => ['flag' => 'referral_visit_commission_taka', 'legacy' => 200],
                'referral_intervention_taka' => ['flag' => 'referral_intervention_commission_taka', 'legacy' => 1000],
                'referral_msk_taka' => ['flag' => 'referral_msk_commission_taka', 'legacy' => 0],
            ];

            foreach ($pairs as $key => $meta) {
                if (array_key_exists($key, $raw)) {
                    continue;
                }

                if (array_key_exists($meta['flag'], $flags) && $flags[$meta['flag']] !== '' && $flags[$meta['flag']] !== null) {
                    $raw[$key] = max(0, (int) $flags[$meta['flag']]);
                } else {
                    $raw[$key] = $meta['legacy'];
                }

                $changed = true;
            }

            if (! $changed) {
                return;
            }

            $tenant->update([
                'practice_rules' => PracticeRules::normalize($raw),
            ]);
        });
    }

    public function down(): void
    {
        // Amounts stay in practice_rules; clearing them would change live money.
    }
};
