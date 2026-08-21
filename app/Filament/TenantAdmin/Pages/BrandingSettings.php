<?php

namespace App\Filament\TenantAdmin\Pages;

use App\Filament\TenantAdmin\Support\PublicMediaFields;
use App\Support\PublicStoredImage;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class BrandingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $title = 'Branding & Website Customization';

    protected static ?string $navigationLabel = 'Branding Settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected string $view = 'filament.tenant-admin.pages.branding-settings';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canManageBranding() ?? false;
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
                'theme_color' => $tenant->cssThemeColor(),
                'font_family' => $tenant->font_family ?? 'Outfit',
                'contact_phone' => $tenant->contact_phone,
                'whatsapp_number' => $tenant->whatsapp_number,
                'review_url' => $tenant->review_url,
                'default_locale' => $tenant->default_locale ?? 'en',
                'call_timeout_seconds' => $tenant->call_timeout_seconds ?? 10,
                'estimated_time_buffer_minutes' => $tenant->estimated_time_buffer_minutes ?? 30,
                'eta_model' => $tenant->eta_model ?? \App\Models\Tenant::ETA_SCHEDULE_GUESS,
                'first_n_patients' => $tenant->first_n_patients ?? 2,
                'first_n_arrival_offset_minutes' => $tenant->first_n_arrival_offset_minutes ?? 15,
                'call_audio_preset' => $tenant->call_audio_preset ?? 'chime',
                'call_audio_path' => $tenant->call_audio_path,
                'call_announce_mode' => $tenant->call_announce_mode ?? \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                'call_announce_locale' => $tenant->call_announce_locale ?? 'en',
                'queue_runner' => $tenant->queue_runner ?? \App\Models\Tenant::QUEUE_RUNNER_STAFF,
                'collect_fee_at_checkin' => (bool) $tenant->collect_fee_at_checkin,
                'patient_booking_horizon_days' => $tenant->patient_booking_horizon_days,
                'pharmacy_doctor_percent' => (int) ($tenant->pharmacy_doctor_percent ?? 0),
            ] + \App\Services\PracticeRules::forBrandingForm($tenant));
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
                            ->extraInputAttributes(['name' => 'name'])
                            ->autocomplete('organization')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('tagline')
                            ->label(__('Tagline / Slogan'))
                            ->maxLength(255)
                            ->placeholder('e.g. Quality healthcare at your fingertips'),
                        PublicMediaFields::image(
                            'logo_url',
                            'branding-logos',
                            __('Logo image'),
                            __('Upload your logo from this computer (JPG, PNG, or WebP, up to 4 MB). An older pasted link still works until you replace it.'),
                        ),
                        PublicMediaFields::image(
                            'favicon_url',
                            'branding-icons',
                            __('Favicon / App Icon'),
                            __('Optional square PNG (or JPG/WebP). Leave empty to use the default health cross icon.'),
                        ),
                    ]),

                Fieldset::make(__('Visual Theme & Typography'))
                    ->schema([
                        ColorPicker::make('theme_color')
                            ->label(__('Primary Brand Color'))
                            ->required()
                            ->regex('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'),
                        Select::make('font_family')
                            ->label(__('Font Family'))
                            ->options([
                                'Outfit' => 'Outfit (Friendly & Contemporary)',
                                'Hind Siliguri' => 'Hind Siliguri (English & Bengali)',
                                'Inter' => 'Inter (Modern & Clean)',
                                'Roboto' => 'Roboto (Classic & Highly Readable)',
                            ])
                            ->default('Outfit')
                            ->required(),
                    ]),

                Fieldset::make(__('Contact & Localization'))
                    ->schema([
                        TextInput::make('contact_phone')
                            ->label(__('Contact Phone'))
                            ->extraInputAttributes(['name' => 'contact_phone'])
                            ->autocomplete('tel')
                            ->tel()
                            ->maxLength(20),
                        TextInput::make('whatsapp_number')
                            ->label(__('WhatsApp Number'))
                            ->extraInputAttributes(['name' => 'whatsapp_number'])
                            ->autocomplete('tel')
                            ->placeholder('8801XXXXXXXXX')
                            ->maxLength(20),
                        TextInput::make('review_url')
                            ->label(__('Google review link'))
                            ->helperText(__('Paste the link Google gives you under Ask for reviews. After a visit, staff can send this with the prescription SMS/WhatsApp, or on its own if you do not use ChamberQ prescriptions. A chamber can override this.'))
                            ->placeholder('https://g.page/r/…/review')
                            ->url()
                            ->maxLength(2048)
                            ->rule(static function () {
                                return static function (string $attribute, mixed $value, \Closure $fail): void {
                                    if (filled($value) && ! \App\Models\Chamber::isGoogleReviewUrl((string) $value)) {
                                        $fail(__('Paste a Google review link, for example https://g.page/r/…/review or a Google Maps share link.'));
                                    }
                                };
                            }),
                        Select::make('default_locale')
                            ->label(__('Chamber language'))
                            ->helperText(__('Book, ticket, waiting-room TV, and this admin panel. Homepage Bangla articles are still the paid bangla_homepage add-on.'))
                            ->options([
                                'en' => 'English',
                                'bn' => 'বাংলা (Bengali)',
                            ])
                            ->default('en')
                            ->required(),
                    ]),

                Fieldset::make(__('Desk'))
                    ->schema([
                        Toggle::make('collect_fee_at_checkin')
                            ->label(__('Collect fee at check-in'))
                            ->helperText(__('When off (usual), staff take the fee after the visit. When on, Collect fee shows for waiting patients on Daily Roster and Live Queue. Each doctor can override this on their profile. No-shows get an automatic cashbook refund if they already paid at the door.')),
                        TextInput::make('patient_booking_horizon_days')
                            ->label(__('How many days ahead patients can book'))
                            ->helperText(fn (): string => __('Website, hero date list, and Book serial. Empty uses the platform Booking window (:days days). Walk-ins at the desk stay today only.', [
                                'days' => \App\Models\PlatformSetting::platformHorizonDays(),
                            ]))
                            ->numeric()
                            ->integer()
                            ->minValue(\App\Models\PlatformSetting::MIN_HORIZON_DAYS)
                            ->maxValue(\App\Models\PlatformSetting::MAX_HORIZON_DAYS)
                            ->nullable(),
                        TextInput::make('pharmacy_doctor_percent')
                            ->label(__('Doctor pharmacy cut (%)'))
                            ->helperText(__('Share of the shop cut for the doctor who wrote the pad. 0 = off. A doctor profile can override this. Walk-in sales with no prescription get ৳0. Paid later from Doctor pharmacy cuts, not at the till.'))
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->visible(fn (): bool => tenant()?->hasPharmacy() ?? false),
                    ]),

                ...\App\Filament\TenantAdmin\Support\PracticeRulesForm::fieldsets(
                    '',
                    fn (): bool => tenant()?->hasStations() ?? false,
                    includeReferral: true,
                    includeFloorRooms: true,
                ),

                Fieldset::make(__('Live Queue Settings'))
                    ->visible(fn (): bool => tenant()?->hasLiveQueue() ?? false)
                    ->schema([
                        TextInput::make('call_timeout_seconds')
                            ->label('Call Timeout (seconds)')
                            ->numeric()
                            ->default(10)
                            ->required(),
                        TextInput::make('estimated_time_buffer_minutes')
                            ->label('Hidden Wait Buffer (minutes)')
                            ->numeric()
                            ->default(30)
                            ->required(),
                        Select::make('eta_model')
                            ->label(__('Waiting time model'))
                            ->helperText(__('How patient ticket “come around” times are calculated for this chamber.'))
                            ->options(\App\Models\Tenant::etaModelOptions())
                            ->default(\App\Models\Tenant::ETA_SCHEDULE_GUESS)
                            ->required(),
                        TextInput::make('first_n_patients')
                            ->label('First N Patients (Early Arrival)')
                            ->numeric()
                            ->default(2)
                            ->required(),
                        TextInput::make('first_n_arrival_offset_minutes')
                            ->label('Early Arrival Offset (minutes)')
                            ->numeric()
                            ->default(15)
                            ->required(),
                        Select::make('queue_runner')
                            ->label(__('Who runs the queue'))
                            ->helperText(__('Staff-run: staff call patients and the doctor’s consult screen follows. Doctor-run: the doctor calls patients; staff see no queue controls. One party at a time.'))
                            ->options(\App\Models\Tenant::queueRunnerOptions())
                            ->default(\App\Models\Tenant::QUEUE_RUNNER_STAFF)
                            ->required(),
                        Select::make('call_announce_mode')
                            ->label(__('When a patient is called'))
                            ->helperText(__('Waiting-room TV and Live Queue Control. Voice plays clear recorded clips (“Number twelve”), not browser speech.'))
                            ->options(\App\Models\Tenant::callAnnounceModeOptions())
                            ->default(\App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE)
                            ->live()
                            ->required(),
                        Select::make('call_announce_locale')
                            ->label(__('Voice language'))
                            ->helperText(__('English uses clear pre-recorded clips (“Number twelve”) — not the spooky browser voice. Same clips play for Bangla for now.'))
                            ->options([
                                'en' => 'English — recorded “Number twelve”',
                                'bn' => 'বাংলা — uses English recording for now (clearer)',
                            ])
                            ->default('en')
                            ->visible(fn (Get $get): bool => in_array($get('call_announce_mode'), [
                                \App\Models\Tenant::ANNOUNCE_VOICE,
                                \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                            ], true))
                            ->required(fn (Get $get): bool => in_array($get('call_announce_mode'), [
                                \App\Models\Tenant::ANNOUNCE_VOICE,
                                \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                            ], true)),
                        Select::make('call_audio_preset')
                            ->label(__('Call chime'))
                            ->helperText(__('Short tone played when a patient is called (if chime is enabled above).'))
                            ->options([
                                'chime' => 'Default chime',
                                'soft-bell' => 'Soft bell',
                                'alert' => 'Alert tone',
                                'custom' => 'Custom upload',
                            ])
                            ->default('chime')
                            ->live()
                            ->visible(fn (Get $get): bool => in_array($get('call_announce_mode'), [
                                \App\Models\Tenant::ANNOUNCE_CHIME,
                                \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                            ], true))
                            ->required(fn (Get $get): bool => in_array($get('call_announce_mode'), [
                                \App\Models\Tenant::ANNOUNCE_CHIME,
                                \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                            ], true)),
                        FileUpload::make('call_audio_path')
                            ->label(__('Custom Call Audio'))
                            ->helperText(__('Upload a short WAV or MP3 (under ~2 MB).'))
                            ->acceptedFileTypes([
                                'audio/mpeg',
                                'audio/mp3',
                                'audio/wav',
                                'audio/x-wav',
                                'audio/wave',
                            ])
                            ->maxSize(2048)
                            ->disk('public')
                            ->directory(fn () => 'call-audio/'.(tenant('id') ?? 'shared'))
                            ->visibility('public')
                            ->visible(fn (Get $get): bool => in_array($get('call_announce_mode'), [
                                \App\Models\Tenant::ANNOUNCE_CHIME,
                                \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                            ], true) && $get('call_audio_preset') === 'custom')
                            ->required(fn (Get $get): bool => in_array($get('call_announce_mode'), [
                                \App\Models\Tenant::ANNOUNCE_CHIME,
                                \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                            ], true) && $get('call_audio_preset') === 'custom'),
                    ]),
            ]);
    }

    /**
     * A FileUpload gives back either a disk path or (single-file) a string;
     * an old pasted https link passes through untouched.
     */
    private static function publicImagePath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = Arr::first($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return PublicStoredImage::toPublicPath($value);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = tenant();

        if ($tenant) {
            $payload = [
                'name' => $data['name'],
                'tagline' => $data['tagline'],
                // Uploads dehydrate to a disk path; the header/tab markup uses
                // these straight as an image source, so store /storage/… .
                'logo_url' => self::publicImagePath($data['logo_url'] ?? null),
                'favicon_url' => self::publicImagePath($data['favicon_url'] ?? null),
                'theme_color' => $data['theme_color'],
                'font_family' => $data['font_family'],
                'contact_phone' => $data['contact_phone'],
                'whatsapp_number' => $data['whatsapp_number'],
                'review_url' => filled($data['review_url'] ?? null)
                    ? \App\Models\Chamber::sanitisedReviewUrl((string) $data['review_url'])
                    : null,
                'default_locale' => $data['default_locale'],
                'collect_fee_at_checkin' => (bool) ($data['collect_fee_at_checkin'] ?? false),
                'patient_booking_horizon_days' => filled($data['patient_booking_horizon_days'] ?? null)
                    ? (int) $data['patient_booking_horizon_days']
                    : null,
                'practice_rules' => \App\Services\PracticeRules::normalize($data),
            ];

            if ($tenant->hasPharmacy()) {
                $payload['pharmacy_doctor_percent'] = max(0, min(100, (int) ($data['pharmacy_doctor_percent'] ?? 0)));
            }

            if ($tenant->hasLiveQueue()) {
                $announceMode = $data['call_announce_mode'] ?? \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE;
                $usesChime = in_array($announceMode, [
                    \App\Models\Tenant::ANNOUNCE_CHIME,
                    \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                ], true);
                $usesVoice = in_array($announceMode, [
                    \App\Models\Tenant::ANNOUNCE_VOICE,
                    \App\Models\Tenant::ANNOUNCE_CHIME_AND_VOICE,
                ], true);

                $preset = $data['call_audio_preset'] ?? $tenant->call_audio_preset ?? 'chime';
                $customPath = $data['call_audio_path'] ?? $tenant->call_audio_path;
                if (is_array($customPath)) {
                    $customPath = $customPath[0] ?? null;
                }

                $payload = array_merge($payload, [
                    'call_timeout_seconds' => $data['call_timeout_seconds'],
                    'estimated_time_buffer_minutes' => $data['estimated_time_buffer_minutes'],
                    'eta_model' => $data['eta_model'],
                    'first_n_patients' => $data['first_n_patients'],
                    'first_n_arrival_offset_minutes' => $data['first_n_arrival_offset_minutes'],
                    'queue_runner' => $data['queue_runner'] ?? \App\Models\Tenant::QUEUE_RUNNER_STAFF,
                    'call_announce_mode' => $announceMode,
                    'call_announce_locale' => $usesVoice ? ($data['call_announce_locale'] ?? 'en') : ($tenant->call_announce_locale ?? 'en'),
                    'call_audio_preset' => $usesChime ? $preset : ($tenant->call_audio_preset ?? 'chime'),
                    'call_audio_path' => $usesChime
                        ? ($preset === 'custom' ? $customPath : null)
                        : $tenant->call_audio_path,
                ]);
            }

            $tenant->update($payload);

            Notification::make()
                ->title(__('Branding settings updated successfully.'))
                ->success()
                ->send();
        }
    }
}
