<?php

namespace App\Filament\TenantAdmin\Pages;

use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class BrandingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $title = 'Branding & Website Customization';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament.tenant-admin.pages.branding-settings';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->isWebDeveloper() ?? false;
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $tenant = tenant();
        if ($tenant) {
            $this->form->fill([
                'name' => $tenant->name,
                'tagline' => $tenant->tagline,
                'logo_url' => $tenant->logo_url,
                'favicon_url' => $tenant->favicon_url,
                'theme_color' => $tenant->theme_color ?? '#0ea5e9',
                'font_family' => $tenant->font_family ?? 'Inter',
                'contact_phone' => $tenant->contact_phone,
                'whatsapp_number' => $tenant->whatsapp_number,
                'default_locale' => $tenant->default_locale ?? 'bn',
            ]);
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Fieldset::make(__('Identity & Header'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Practice / Clinic Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('tagline')
                            ->label(__('Tagline / Slogan'))
                            ->maxLength(255)
                            ->placeholder('e.g. Quality healthcare at your fingertips'),
                        TextInput::make('logo_url')
                            ->label(__('Logo Image URL'))
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://example.com/logo.png'),
                        TextInput::make('favicon_url')
                            ->label(__('Favicon / App Icon URL'))
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://example.com/icon.png'),
                    ]),

                Fieldset::make(__('Visual Theme & Typography'))
                    ->schema([
                        ColorPicker::make('theme_color')
                            ->label(__('Primary Brand Color'))
                            ->required(),
                        Select::make('font_family')
                            ->label(__('Font Family'))
                            ->options([
                                'Inter' => 'Inter (Modern & Clean)',
                                'Outfit' => 'Outfit (Friendly & Contemporary)',
                                'Roboto' => 'Roboto (Classic & Highly Readable)',
                                'Hind Siliguri' => 'Hind Siliguri (Dual English & Bengali)',
                            ])
                            ->default('Inter')
                            ->required(),
                    ]),

                Fieldset::make(__('Contact & Localization'))
                    ->schema([
                        TextInput::make('contact_phone')
                            ->label(__('Contact Phone'))
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp Number'))
                            ->placeholder('8801XXXXXXXXX')
                            ->maxLength(20),
                        Select::make('default_locale')
                            ->label(__('Default Website Language'))
                            ->options([
                                'en' => 'English',
                                'bn' => 'বাংলা (Bengali)',
                            ])
                            ->default('bn')
                            ->required(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = tenant();

        if ($tenant) {
            $tenant->update([
                'name' => $data['name'],
                'tagline' => $data['tagline'],
                'logo_url' => $data['logo_url'],
                'favicon_url' => $data['favicon_url'],
                'theme_color' => $data['theme_color'],
                'font_family' => $data['font_family'],
                'contact_phone' => $data['contact_phone'],
                'whatsapp_number' => $data['whatsapp_number'],
                'default_locale' => $data['default_locale'],
            ]);

            Notification::make()
                ->title(__('Branding settings updated successfully.'))
                ->success()
                ->send();
        }
    }
}
