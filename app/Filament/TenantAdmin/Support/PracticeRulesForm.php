<?php

namespace App\Filament\TenantAdmin\Support;

use App\Services\PracticeRules;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

final class PracticeRulesForm
{
    /**
     * @return list<Fieldset>
     */
    public static function fieldsets(string $prefix = '', mixed $visible = true, bool $includeReferral = false, bool $includeFloorRooms = false): array
    {
        $key = fn (string $name): string => $prefix === '' ? $name : $prefix.'.'.$name;

        $sets = [
            Fieldset::make(__('Follow-up window'))
                ->schema([
                    Select::make($key('follow_up_window'))
                        ->label(__('When a return visit is still a follow-up'))
                        ->helperText(__('If they come back after this window, the floor treats them as a new visit again — 2nd, 3rd, or 10th time does not matter. Unlimited never expires. Never means every return is a new visit.'))
                        ->options([
                            PracticeRules::FOLLOW_UP_MONTHS => __('For a number of months'),
                            PracticeRules::FOLLOW_UP_UNLIMITED => __('Always a follow-up (no expiry)'),
                            PracticeRules::FOLLOW_UP_NEVER => __('Always a new visit'),
                        ])
                        ->default(PracticeRules::FOLLOW_UP_MONTHS)
                        ->live()
                        ->native(false)
                        ->required(),
                    TextInput::make($key('follow_up_months'))
                        ->label(__('Follow-up lasts this many months'))
                        ->helperText(__('Type this clinic’s number. Another chamber’s 3 months is not yours unless you choose it.'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120)
                        ->default(3)
                        ->visible(fn (Get $get): bool => $get($key('follow_up_window')) === PracticeRules::FOLLOW_UP_MONTHS)
                        ->required(fn (Get $get): bool => $get($key('follow_up_window')) === PracticeRules::FOLLOW_UP_MONTHS),
                ]),
            Fieldset::make(__('Report review fee'))
                ->schema(self::roomPricingFields($key, 'report', __('Report'))),
            Fieldset::make(__('Counseling fee'))
                ->schema(self::roomPricingFields($key, 'counseling', __('Counseling'))),
        ];

        if ($includeFloorRooms) {
            array_unshift($sets, Fieldset::make(__('Rooms on a clinic day'))
                ->schema([
                    Toggle::make($key('floor_lab'))
                        ->label(__('Lab room'))
                        ->helperText(__('Open whenever this clinic is sitting — not a separate session. Send to lab picks the test type (MSK today; another clinic adds its own).'))
                        ->default(false),
                    Toggle::make($key('floor_report'))
                        ->label(__('Report room'))
                        ->helperText(__('Same idea: a room on an open day, not its own sitting hours.'))
                        ->default(false),
                    Toggle::make($key('floor_counseling'))
                        ->label(__('Counseling'))
                        ->helperText(__('A list for today. It can be the same physical room as visit or OT. Tick the next box only if this clinic runs counseling on its own clock.'))
                        ->live()
                        ->default(false),
                    Toggle::make($key('counseling_as_session'))
                        ->label(__('Counseling has its own sitting hours'))
                        ->helperText(__('Leave off for most clinics. On only if you add Counseling on Schedule Sessions with its own start and end.'))
                        ->visible(fn (Get $get): bool => (bool) $get($key('floor_counseling')))
                        ->default(false),
                ]));
        }

        if ($visible !== true) {
            $sets = array_map(function (Fieldset $set) use ($visible): Fieldset {
                return $set->visible($visible);
            }, $sets);
        }

        if ($includeReferral) {
            $sets[] = Fieldset::make(__('Outside GP cut'))
                ->visible(fn (): bool => tenant()?->hasReferrals() ?? false)
                ->schema([
                    TextInput::make($key('referral_visit_taka'))
                        ->label(__('Cut per visit (৳)'))
                        ->helperText(__('What this clinic owes an outside GP when a referred visit fee is collected. 0 means nothing is owed. Not ChamberQ’s ৳200 unless you type 200.'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    TextInput::make($key('referral_intervention_taka'))
                        ->label(__('Cut per intervention (৳)'))
                        ->helperText(__('Same idea for a procedure fee. Type this clinic’s amount.'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                    TextInput::make($key('referral_msk_taka'))
                        ->label(__('Cut per MSK scan (৳)'))
                        ->helperText(__('0 means the GP gets nothing for a scan. Type a number only if this clinic pays one.'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ]);
        }

        return $sets;
    }

    /**
     * @param  callable(string): string  $key
     * @return list<\Filament\Forms\Components\Component>
     */
    private static function roomPricingFields(callable $key, string $prefix, string $room): array
    {
        $pricing = $key($prefix.'_pricing');

        return [
            Select::make($pricing)
                ->label(__(':room pricing', ['room' => $room]))
                ->options([
                    PracticeRules::PRICING_ALWAYS_FREE => __('Always free'),
                    PracticeRules::PRICING_ALWAYS_PAID => __('Always paid'),
                    PracticeRules::PRICING_TIMED => __('Free or one price for a while, then another price'),
                ])
                ->default(PracticeRules::PRICING_ALWAYS_FREE)
                ->live()
                ->native(false)
                ->required(),
            TextInput::make($key($prefix.'_free_for_months'))
                ->label(__('First price lasts this many months'))
                ->helperText(__('Counted from the visit that started this path (or the last completed visit).'))
                ->numeric()
                ->minValue(1)
                ->maxValue(120)
                ->default(3)
                ->visible(fn (Get $get): bool => $get($pricing) === PracticeRules::PRICING_TIMED),
            TextInput::make($key($prefix.'_price_inside_taka'))
                ->label(__('Price during that time (৳)'))
                ->helperText(__('0 means free.'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->visible(fn (Get $get): bool => $get($pricing) === PracticeRules::PRICING_TIMED),
            TextInput::make($key($prefix.'_price_after_taka'))
                ->label(__('Price after that time (৳)'))
                ->helperText(__('For “always paid”, this is the fee. 0 means free.'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->visible(fn (Get $get): bool => in_array($get($pricing), [PracticeRules::PRICING_TIMED, PracticeRules::PRICING_ALWAYS_PAID], true)),
        ];
    }
}
