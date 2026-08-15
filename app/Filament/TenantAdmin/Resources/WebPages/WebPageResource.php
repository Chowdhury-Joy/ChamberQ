<?php

namespace App\Filament\TenantAdmin\Resources\WebPages;

use App\Filament\TenantAdmin\Resources\WebPages\Pages\CreateWebPage;
use App\Filament\TenantAdmin\Resources\WebPages\Pages\EditWebPage;
use App\Filament\TenantAdmin\Resources\WebPages\Pages\ListWebPages;
use App\Filament\TenantAdmin\Resources\WebPages\Tables\WebPagesTable;
use App\Filament\TenantAdmin\Support\PageBuilderChrome;
use App\Filament\TenantAdmin\Support\PublicMediaFields;
use App\Models\WebPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Actions\Action;

class WebPageResource extends Resource
{
    protected static ?string $model = WebPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Web Pages';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return ($user?->canManageContent() ?? false)
            && (tenant()?->hasFrontDoor() ?? false);
    }

    public static function canCreate(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canManagePageStructure() ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canManagePageStructure() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $canStructure = fn (): bool => auth()->user()?->canManagePageStructure() ?? false;

        return $schema
            ->schema([
                Section::make('Page')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->default('/')
                            ->disabled(fn () => ! $canStructure()),
                        Forms\Components\Toggle::make('is_published')
                            ->required()
                            ->default(true)
                            ->disabled(fn () => ! $canStructure())
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Builder::make('content')
                    ->label('Page Sections')
                    ->collapsible()
                    ->collapsed()
                    ->cloneable($canStructure)
                    ->blockNumbers(false)
                    ->collapseAllAction(function (Action $action, $livewire): Action {
                        return $action
                            ->button()
                            ->color('gray')
                            ->hidden(PageBuilderChrome::isHomepageEditor($livewire instanceof \Livewire\Component ? $livewire : null));
                    })
                    ->expandAllAction(function (Action $action, $livewire): Action {
                        return $action
                            ->button()
                            ->color('gray')
                            ->hidden(PageBuilderChrome::isHomepageEditor($livewire instanceof \Livewire\Component ? $livewire : null));
                    })
                    ->addable($canStructure)
                    ->deletable($canStructure)
                    ->reorderable($canStructure)
                    ->addActionLabel('+ Add Section Block')
                    ->extraItemActions([
                        Action::make('toggleHide')
                            ->label(fn (array $state): string => (!empty($state['is_hidden']) || !empty($state['data']['is_hidden'])) ? 'Unhide' : 'Hide')
                            ->tooltip(fn (array $state): string => (!empty($state['is_hidden']) || !empty($state['data']['is_hidden'])) ? 'Section is HIDDEN (Click to show)' : 'Section is VISIBLE (Click to hide)')
                            ->icon(fn (array $state): string => (!empty($state['is_hidden']) || !empty($state['data']['is_hidden'])) ? 'heroicon-m-eye-slash' : 'heroicon-m-eye')
                            ->color(fn (array $state): string => (!empty($state['is_hidden']) || !empty($state['data']['is_hidden'])) ? 'danger' : 'gray')
                            ->visible($canStructure)
                            ->action(function (array $arguments, Forms\Components\Builder $component): void {
                                $items = $component->getState();
                                $itemKey = $arguments['item'] ?? null;
                                if ($itemKey && isset($items[$itemKey])) {
                                    $currentlyHidden = !empty($items[$itemKey]['is_hidden']) || !empty($items[$itemKey]['data']['is_hidden']);
                                    $items[$itemKey]['is_hidden'] = ! $currentlyHidden;
                                    $items[$itemKey]['data']['is_hidden'] = ! $currentlyHidden;
                                    $component->state($items);

                                    if ($currentlyHidden) {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Section is now Visible on Live Website')
                                            ->success()
                                            ->send();
                                    } else {
                                        \Filament\Notifications\Notification::make()
                                            ->title('Section is now Hidden from Live Website')
                                            ->warning()
                                            ->send();
                                    }
                                }
                            }),
                    ])
                    ->blocks([
                        // 1. Hero Banner (50/50 Split)
                        Forms\Components\Builder\Block::make('hero')
                            ->label(PageBuilderChrome::blockLid('Hero'))
                            ->columns(2)
                            ->schema([
                                Forms\Components\Textarea::make('headline')
                                    ->required()
                                    ->rows(2)
                                    ->helperText('On phones, line breaks show as a two-line name. On larger screens it stays one line.')
                                    ->default('Care that respects your time')
                                    ->columnSpan(2),
                                Forms\Components\Textarea::make('subheadline')->default('Book a serial online and follow the live queue from your phone — pay at the chamber.')->columnSpan(2),
                                Forms\Components\TextInput::make('backed_lead')->label('Backed-by lead')->placeholder('Backed by')->default('Backed by'),
                                Forms\Components\TextInput::make('backed_strong')->label('Backed-by emphasis')->placeholder('8+ Physiotherapists'),
                                Forms\Components\TextInput::make('rating_score')->label('Hero rating score')->placeholder('4.9*'),
                                Forms\Components\TextInput::make('rating_copy')->label('Hero rating copy')->placeholder('Patients trust our care!'),
                                Forms\Components\TextInput::make('credentials')->label('Credentials (solo hero)')->placeholder('MBBS, FCPS (Medicine)'),
                                Forms\Components\TextInput::make('role_location')->label('Role & Location (solo hero)')->placeholder('Consultant Physician, Dhanmondi'),
                                Forms\Components\TextInput::make('cta_text')->default('Book Appointment'),
                                Forms\Components\TextInput::make('cta_link')->default('/book'),
                                Forms\Components\TextInput::make('secondary_cta_text')->default('Our Services'),
                                Forms\Components\TextInput::make('secondary_cta_link')->default('#services'),
                                Forms\Components\TextInput::make('emergency_phone')->label('Emergency Hotline (shown below hero)')->placeholder('017XXXXXXXX'),
                                self::heroImageUpload(),
                            ]),

                        // 2. Text-Based Trust Bar
                        Forms\Components\Builder\Block::make('trust_bar')
                            ->label(PageBuilderChrome::blockLid('Trust Bar'))
                            ->schema([
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('badges')
                                        ->columns(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('text_badge')->required()->placeholder('Board Certified Physicians')->columnSpan(2),
                                        ])
                                        ->default([
                                            ['text_badge' => 'Board Certified Physicians'],
                                            ['text_badge' => '24/7 Emergency Care'],
                                            ['text_badge' => 'HIPAA Compliant & Secure'],
                                            ['text_badge' => 'Top Rated Medical Clinic'],
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'text_badge'),
                                ),
                            ]),

                        // 3. Patient Care Journey (3 to 5 steps)
                        Forms\Components\Builder\Block::make('patient_journey')
                            ->label(PageBuilderChrome::blockLid('Patient Journey'))
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Your Patient Care Journey'),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('steps')
                                        ->columns(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('step_number')->required(),
                                            Forms\Components\TextInput::make('title')->required(),
                                            Forms\Components\Textarea::make('description')->columnSpan(2),
                                        ])
                                        ->minItems(3)
                                        ->maxItems(5)
                                        ->default([
                                            ['step_number' => '01', 'title' => 'Book Online or Call', 'description' => 'Select your doctor or specialty and pick a convenient time.'],
                                            ['step_number' => '02', 'title' => 'Consultation & Diagnosis', 'description' => 'Comprehensive evaluation with our experienced medical staff.'],
                                            ['step_number' => '03', 'title' => 'Personalized Care Plan', 'description' => 'Receive tailored treatment, prescriptions, and follow-up guidance.'],
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::numberedName($state, 'step_number', 'title'),
                                ),
                            ]),

                        // 4. Services & Specialties Matrix (Cards < 8, List >= 8)
                        Forms\Components\Builder\Block::make('service_matrix')
                            ->label(PageBuilderChrome::blockLid('Services'))
                            ->schema([
                                Forms\Components\Placeholder::make('service_matrix_source')
                                    ->label(__('Content source'))
                                    ->content(__('Cards are loaded from published Departments in Website → Departments.'))
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('heading')->default('Our Clinical Services'),
                                Forms\Components\TextInput::make('footer_text')
                                    ->label('Carousel footer caption')
                                    ->default('Explore our services and book the one you need online.')
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('view_all_text')->label('Header CTA label')->default('View all services →'),
                                Forms\Components\TextInput::make('view_all_link')->label('Header CTA link')->default('/departments'),
                            ]),

                        // 5. Doctor & Medical Team Directory (Clinic Only)
                        Forms\Components\Builder\Block::make('doctor_grid')
                            ->label(PageBuilderChrome::blockLid('Doctor Directory'))
                            ->columns(2)
                            ->schema([
                                Forms\Components\Placeholder::make('doctor_grid_source')
                                    ->label(__('Content source'))
                                    ->content(__('Cards are loaded from doctors with “Show on website” enabled in the Doctors resource.'))
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('eyebrow')->default('Our physiotherapists'),
                                Forms\Components\TextInput::make('heading')->default('Meet The Experts Behind Your Recovery')->columnSpanFull(),
                                Forms\Components\TextInput::make('view_all_text')->label('Header CTA label')->default('View all doctors →'),
                                Forms\Components\TextInput::make('view_all_link')->label('Header CTA link')->default('/doctors'),
                                Forms\Components\TextInput::make('stats_heading')
                                    ->label('Stats band heading')
                                    ->placeholder('Trusted Physiotherapy Centre In Panchlaish, Chattogram')
                                    ->columnSpanFull(),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('stats')
                                        ->label('Stats band (shown under doctor cards)')
                                        ->columnSpanFull()
                                        ->columns(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('value')->required()->placeholder('8'),
                                            Forms\Components\TextInput::make('label')->required()->placeholder('+ Expert Physiotherapists'),
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::numberedName($state, 'value', 'label'),
                                ),
                            ]),

                        // 6. Interactive Appointment Wizard
                        Forms\Components\Builder\Block::make('appointment_wizard')
                            ->label(PageBuilderChrome::blockLid('Appointment Wizard'))
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Book Your Appointment in 60 Seconds'),
                                Forms\Components\TextInput::make('subheadline')->default('Select specialty, physician, date and confirm your booking instantly.'),
                            ]),

                        // 7. Clinic Locations & Hours (Google Maps Link)
                        Forms\Components\Builder\Block::make('location_hours')
                            ->label(PageBuilderChrome::blockLid('Locations & Hours'))
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Visit Our Clinic')->columnSpan(2),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('locations')
                                        ->label('Branches (preferred for multi-location clinics)')
                                        ->columnSpan(2)
                                        ->columns(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('name')->label('Branch name')->required()->columnSpan(2),
                                            Forms\Components\TextInput::make('address')->columnSpan(2),
                                            Forms\Components\TextInput::make('operating_hours')->default('Sat–Thu: 9:00 AM – 8:00 PM'),
                                            Forms\Components\TextInput::make('phone'),
                                            Forms\Components\TextInput::make('google_maps_url')->label('Google Maps link')->columnSpan(2),
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'name'),
                                ),
                                Forms\Components\TextInput::make('google_maps_url')->label('Single-location Google Maps link (fallback)')->placeholder('https://maps.google.com/?q=...')->columnSpan(2),
                                Forms\Components\TextInput::make('address')->placeholder('123 Health Ave, Suite 400, Medical City'),
                                Forms\Components\TextInput::make('operating_hours')->default('Mon - Fri: 8:00 AM - 8:00 PM | Sat: 9:00 AM - 4:00 PM'),
                                Forms\Components\TextInput::make('phone')->placeholder('+1 (555) 234-5678'),
                                Forms\Components\TextInput::make('email')->placeholder('contact@clinic.com'),
                            ]),

                        // 8. Latest Educational Videos (Max 10)
                        Forms\Components\Builder\Block::make('video_gallery')
                            ->label(PageBuilderChrome::blockLid('Videos'))
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Latest Educational Videos'),
                                Forms\Components\TextInput::make('follow_text')->label('Follow CTA label')->default('Follow for More'),
                                Forms\Components\TextInput::make('follow_url')->label('Follow CTA URL')->placeholder('https://www.youtube.com/@...'),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('videos')
                                    ->label('Videos')
                                    ->columns(2)
                                    ->maxItems(10)
                                    ->schema([
                                        Forms\Components\TextInput::make('title')
                                            ->required()
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('type')
                                            ->label('Media')
                                            ->options([
                                                'link' => 'YouTube / Facebook / Instagram link',
                                                'upload' => 'Upload a video from this computer (up to 20 MB)',
                                            ])
                                            ->default('link')
                                            ->live()
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('video_url')
                                            ->label('Video link')
                                            ->placeholder('https://www.youtube.com/watch?v=...')
                                            ->visible(fn (Get $get): bool => ($get('type') ?? 'link') !== 'upload')
                                            ->dehydrated(fn (Get $get): bool => ($get('type') ?? 'link') !== 'upload')
                                            ->columnSpanFull(),
                                        PublicMediaFields::video(
                                            'uploaded_video',
                                            'webpage-videos',
                                            'Video file',
                                            'MP4, WebM, or MOV, up to 20 MB. Patients tap the card to watch.',
                                        )
                                            ->visible(fn (Get $get): bool => $get('type') === 'upload')
                                            ->required(fn (Get $get): bool => $get('type') === 'upload')
                                            ->dehydrated(fn (Get $get): bool => $get('type') === 'upload')
                                            ->columnSpanFull(),
                                        PublicMediaFields::image(
                                            'thumbnail_url',
                                            'webpage-video-thumbs',
                                            'Cover image',
                                            'The still photo on the card (same look as Latest Educational Videos). JPG, PNG, or WebP, up to 4 MB.',
                                        )->columnSpanFull(),
                                    ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'title'),
                                ),
                            ]),

                        // 9. Image Carousel / Slider with Configurable Aspect Ratio
                        Forms\Components\Builder\Block::make('image_carousel')
                            ->label(PageBuilderChrome::blockLid('Photo Gallery'))
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Facility Tour & Photo Gallery'),
                                Forms\Components\Select::make('aspect_ratio')
                                    ->label('Image Aspect Ratio')
                                    ->options([
                                        '16:9' => '16:9 (Widescreen)',
                                        '5:4' => '5:4 (Standard Landscape)',
                                        '4:3' => '4:3 (Traditional)',
                                        '1:1' => '1:1 (Square)',
                                        '21:9' => '21:9 (Cinematic Ultra-wide)',
                                    ])
                                    ->default('16:9')
                                    ->required(),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('items')
                                        ->columns(2)
                                        ->columnSpan(2)
                                        ->schema([
                                            PublicMediaFields::image(
                                                'image_url',
                                                'webpage-gallery',
                                                'Slide image',
                                                'Upload a photo from this computer (JPG, PNG, or WebP, up to 4 MB). An older pasted link still works until you replace it.',
                                            )->required()->columnSpanFull(),
                                            Forms\Components\TextInput::make('title')->label('Caption Title'),
                                            Forms\Components\TextInput::make('description')->label('Caption Subtitle'),
                                            Forms\Components\TextInput::make('link_url')->label('Click Link (Optional)'),
                                        ])
                                        ->default([
                                            [
                                                'image_url' => 'https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?auto=format&fit=crop&w=1200&q=80',
                                                'title' => 'Modern Reception Lounge',
                                                'description' => 'Comfortable waiting environment for patients and families.',
                                            ],
                                            [
                                                'image_url' => 'https://images.unsplash.com/photo-1581594693702-fbdc51b2763b?auto=format&fit=crop&w=1200&q=80',
                                                'title' => 'Advanced Diagnostic Lab',
                                                'description' => 'State-of-the-art diagnostic facilities and fast testing.',
                                            ],
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'title', 'description') ?? 'Untitled image',
                                ),
                            ]),

                        // 10. Conditions Treated Library
                        Forms\Components\Builder\Block::make('condition_library')
                            ->label(PageBuilderChrome::blockLid('Conditions'))
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Conditions We Treat'),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('conditions')
                                        ->schema([
                                            Forms\Components\TextInput::make('name')->required(),
                                            Forms\Components\Textarea::make('description'),
                                            PageBuilderChrome::lid(
                                                Forms\Components\Repeater::make('features')
                                                    ->label('Included treatments / focus areas')
                                                    ->simple(
                                                        Forms\Components\TextInput::make('label')->required()
                                                    )
                                                    ->default([]),
                                                fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'label'),
                                            ),
                                        ])
                                        ->default([
                                            ['name' => 'Hypertension & Cardiac Health', 'description' => 'Comprehensive blood pressure monitoring and heart care.', 'features' => []],
                                            ['name' => 'Diabetes & Endocrine Disorders', 'description' => 'Personalized management plans for diabetes care.', 'features' => []],
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'name'),
                                ),
                            ]),

                        // 10b. About the Doctor (solo person-led)
                        Forms\Components\Builder\Block::make('about_doctor')
                            ->label(PageBuilderChrome::blockLid('About the Doctor'))
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Meet Your Doctor')->columnSpanFull(),
                                Forms\Components\Textarea::make('subheadline')->columnSpanFull(),
                                Forms\Components\TextInput::make('cta_text')->default('Book Appointment'),
                                Forms\Components\TextInput::make('cta_link')->default('/book'),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('highlights')
                                        ->columns(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('title')->required(),
                                            Forms\Components\Textarea::make('description')->required()->columnSpan(2),
                                        ])
                                        ->default([
                                            ['title' => 'Study', 'description' => 'FCPS (Medicine); MD training from leading Dhaka teaching hospitals.'],
                                            ['title' => 'Awards & Honors', 'description' => 'Recognized for clinical teaching and patient-centred medicine.'],
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'title'),
                                ),
                            ]),

                        // 10c. Patient Testimonials
                        Forms\Components\Builder\Block::make('testimonials')
                            ->label(PageBuilderChrome::blockLid('Testimonials'))
                            ->schema([
                                Forms\Components\TextInput::make('eyebrow')->default('Recovery stories'),
                                Forms\Components\TextInput::make('heading')->default('What our patients say'),
                                Forms\Components\TextInput::make('promo_text')->label('Footer promo link label')->default('Follow us for health tips →'),
                                Forms\Components\TextInput::make('promo_link')->label('Footer promo URL')->placeholder('https://facebook.com/...'),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('items')
                                        ->schema([
                                            Forms\Components\Textarea::make('quote')->required()->columnSpanFull(),
                                            Forms\Components\TextInput::make('name')->required(),
                                            Forms\Components\TextInput::make('label')->default('Verified Patient'),
                                            PublicMediaFields::image(
                                                'photo_url',
                                                'webpage-testimonials',
                                                'Patient photo',
                                                'Optional headshot beside the quote (JPG, PNG, or WebP, up to 4 MB).',
                                            )->columnSpanFull(),
                                        ])
                                        ->default([
                                            [
                                                'quote' => 'Every visit is calm and clear — I leave knowing exactly what to do next.',
                                                'name' => 'Rashida Begum',
                                                'label' => 'Verified Patient',
                                            ],
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'name', 'quote'),
                                ),
                            ]),

                        // 11. FAQ Accordion
                        Forms\Components\Builder\Block::make('faq')
                            ->label(PageBuilderChrome::blockLid('FAQ'))
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Frequently Asked Questions'),
                                PublicMediaFields::image(
                                    'promo_image_url',
                                    'webpage-faq',
                                    'Side panel image',
                                    'The photo beside the questions (JPG, PNG, or WebP, up to 4 MB).',
                                )->columnSpanFull(),
                                Forms\Components\TextInput::make('promo_heading')->label('Side panel heading')->default('Need care? Book an appointment'),
                                Forms\Components\TextInput::make('promo_cta_text')->label('Side panel CTA label')->default('Get in touch'),
                                Forms\Components\TextInput::make('promo_cta_link')->label('Side panel CTA link')->default('/book'),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('faqs')
                                        ->columns(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('question')->required()->columnSpan(2),
                                            Forms\Components\Textarea::make('answer')->required()->columnSpan(2),
                                        ])
                                        ->default([
                                            ['question' => 'How do I prepare for my first appointment?', 'answer' => 'Please bring your photo ID, insurance card, and any relevant past medical records.'],
                                            ['question' => 'What is the cancellation policy?', 'answer' => 'You can reschedule or cancel up to 24 hours prior to your scheduled time without fee.'],
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'question'),
                                ),
                            ]),

                        // 12. About Practice & Facility Tour (Clinic Only)
                        Forms\Components\Builder\Block::make('about_facility')
                            ->label(PageBuilderChrome::blockLid('About Practice'))
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('About Our Practice & Facilities')->columnSpan(2),
                                Forms\Components\Textarea::make('mission_statement')->default('Dedicated to providing world-class medical treatment with compassion, innovation, and integrity.')->columnSpan(2),
                                Forms\Components\TextInput::make('cta_text')->default('More about us'),
                                Forms\Components\TextInput::make('cta_link')->default('/book'),
                                Forms\Components\TextInput::make('trust_copy')->label('Trust line lead')->default('Trusted by'),
                                Forms\Components\TextInput::make('trust_strong')->label('Trust line emphasis')->default('patients across the city'),
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('gallery')
                                        ->columns(2)
                                        ->columnSpan(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('title')->required(),
                                            PublicMediaFields::image(
                                                'image_url',
                                                'webpage-facility',
                                                'Icon / image',
                                                'Upload the picture for this card (JPG, PNG, or WebP, up to 4 MB).',
                                            )->required()->columnSpan(2),
                                            Forms\Components\Textarea::make('description')->columnSpan(2),
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::nestedName($state, 'title') ?? 'Untitled image',
                                ),
                            ]),

                        // 13. Healthcare Metric Band (Clinic Only)
                        Forms\Components\Builder\Block::make('stat_band')
                            ->label(PageBuilderChrome::blockLid('Stats'))
                            ->schema([
                                PageBuilderChrome::lid(
                                    Forms\Components\Repeater::make('stats')
                                        ->columns(2)
                                        ->schema([
                                            Forms\Components\TextInput::make('value')->required()->placeholder('99%'),
                                            Forms\Components\TextInput::make('label')->required()->placeholder('Patient Satisfaction'),
                                        ])
                                        ->default([
                                            ['value' => '99%', 'label' => 'Patient Satisfaction'],
                                            ['value' => '15+', 'label' => 'Years Serving Community'],
                                            ['value' => '50,000+', 'label' => 'Patients Treated'],
                                            ['value' => '24/7', 'label' => 'Emergency Support'],
                                        ]),
                                    fn (array $state): ?string => PageBuilderChrome::numberedName($state, 'value', 'label'),
                                ),
                            ]),

                        // 14. Health Insights & Articles
                        Forms\Components\Builder\Block::make('health_insights')
                            ->label(PageBuilderChrome::blockLid('Health Insights'))
                            ->schema([
                                Forms\Components\Placeholder::make('health_insights_source')
                                    ->label(__('Content source'))
                                    ->content(__('Cards are loaded from published Blog posts in Website → Blog posts.'))
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('heading')->default('Latest Health Insights & Articles'),
                                Forms\Components\TextInput::make('view_all_text')->label('Header CTA label')->default('View all posts →'),
                                Forms\Components\TextInput::make('view_all_link')->label('Header CTA link')->default('/blog'),
                            ]),

                        // 15. High-Converting Action Banner
                        Forms\Components\Builder\Block::make('cta_banner')
                            ->label(PageBuilderChrome::blockLid('CTA Banner'))
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('headline')->default('Need Same-Day Care? Book Online in 60 Seconds')->columnSpan(2),
                                Forms\Components\TextInput::make('subheadline')->default('Our team is ready to provide immediate medical attention.')->columnSpan(2),
                                Forms\Components\TextInput::make('cta_text')->default('Book Your Appointment Now'),
                                Forms\Components\TextInput::make('cta_link')->default('/book'),
                                Forms\Components\TextInput::make('trust_phone')->label('Trust line phone')->placeholder('01630-078675'),
                                Forms\Components\TextInput::make('trust_address')->label('Trust line address')->placeholder('553 O.R. Nizam Road, GEC, Panchlaish'),
                            ]),

                        // 16. Privacy & Policy Text Block (Clinic Only - Technical HTML)
                        Forms\Components\Builder\Block::make('rich_text')
                            ->label(PageBuilderChrome::blockLid('Rich Text'))
                            ->visible(fn () => auth()->user()?->canManagePageStructure() ?? false)
                            ->schema([
                                Forms\Components\RichEditor::make('content')->required(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function heroImageUpload(): Forms\Components\FileUpload
    {
        return PublicMediaFields::image(
            'image_url',
            'webpage-hero',
            'Hero image',
            'Upload a photo from this computer (JPG, PNG, or WebP, up to 4 MB). An older pasted link still works until you replace it.',
        )->columnSpan(2);
    }

    public static function table(Table $table): Table
    {
        return WebPagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebPages::route('/'),
            'create' => CreateWebPage::route('/create'),
            'edit' => EditWebPage::route('/{record}/edit'),
        ];
    }
}
