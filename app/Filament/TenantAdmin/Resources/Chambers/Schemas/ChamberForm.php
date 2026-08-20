<?php

namespace App\Filament\TenantAdmin\Resources\Chambers\Schemas;

use App\Models\Chamber;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ChamberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->extraInputAttributes(['name' => 'name'])
                    ->autocomplete('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('address')
                    ->extraInputAttributes(['name' => 'address'])
                    ->autocomplete('street-address')
                    ->columnSpanFull()
                    ->maxLength(500),
                TextInput::make('map_url')
                    ->label(__('Google Maps link'))
                    ->helperText(__('Open the chamber in Google Maps, tap Share, and paste the link here. Leave empty to use the address above.'))
                    ->placeholder('https://maps.app.goo.gl/…')
                    ->url()
                    ->maxLength(2048)
                    ->columnSpanFull()
                    ->rule(static function () {
                        return static function (string $attribute, mixed $value, \Closure $fail): void {
                            if (filled($value) && ! Chamber::isGoogleMapsUrl((string) $value)) {
                                $fail(__('Paste a Google Maps link, for example https://maps.app.goo.gl/… or https://www.google.com/maps/…'));
                            }
                        };
                    }),
                TextInput::make('review_url')
                    ->label(__('Google review link'))
                    ->helperText(__('Paste the link Google gives you under Ask for reviews (g.page or Maps). After a visit, staff can send this with the prescription, or on its own if you write prescriptions on paper. Overrides the Branding link for this chamber.'))
                    ->placeholder('https://g.page/r/…/review')
                    ->url()
                    ->maxLength(2048)
                    ->columnSpanFull()
                    ->rule(static function () {
                        return static function (string $attribute, mixed $value, \Closure $fail): void {
                            if (filled($value) && ! Chamber::isGoogleReviewUrl((string) $value)) {
                                $fail(__('Paste a Google review link, for example https://g.page/r/…/review or a Google Maps share link.'));
                            }
                        };
                    }),
                KeyValue::make('hours')
                    ->label(__('Operating Hours'))
                    ->keyLabel(__('Day'))
                    ->valueLabel(__('Hours'))
                    ->keyPlaceholder('e.g. Saturday')
                    ->valuePlaceholder('e.g. 09:00–17:00')
                    ->columnSpanFull(),
                TextInput::make('contact')
                    ->extraInputAttributes(['name' => 'contact'])
                    ->autocomplete('tel')
                    ->tel()
                    ->maxLength(20),
            ]);
    }
}
