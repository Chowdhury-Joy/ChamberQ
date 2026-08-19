<?php

namespace App\Filament\TenantAdmin\Support;

use App\Services\PracticeRules;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

final class PracticeRulesForm
{
    /**
     * @return list<Fieldset>
     */
    public static function fieldsets(string $prefix = '', mixed $visible = true): array
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

        if ($visible !== true) {
            $sets = array_map(function (Fieldset $set) use ($visible): Fieldset {
                return $set->visible($visible);
            }, $sets);
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
