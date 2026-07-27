<?php

namespace App\Filament\TenantAdmin\Resources\WebPages;

use App\Filament\TenantAdmin\Resources\WebPages\Pages\CreateWebPage;
use App\Filament\TenantAdmin\Resources\WebPages\Pages\EditWebPage;
use App\Filament\TenantAdmin\Resources\WebPages\Pages\ListWebPages;
use App\Filament\TenantAdmin\Resources\WebPages\Tables\WebPagesTable;
use App\Models\WebPage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms;
use Filament\Actions\Action;

class WebPageResource extends Resource
{
    protected static ?string $model = WebPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->canManageContent() ?? false;
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
                    ->disabled(fn () => ! $canStructure()),
                Forms\Components\Builder::make('content')
                    ->label('Page Sections')
                    ->collapsible()
                    ->cloneable($canStructure)
                    ->blockNumbers(true)
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
                            ->label('Hero Banner (50/50 Content & Image)')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('headline')->required()->default('Care that respects your time')->columnSpan(2),
                                Forms\Components\Textarea::make('subheadline')->default('Book a serial online and follow the live queue from your phone — pay at the chamber.')->columnSpan(2),
                                Forms\Components\TextInput::make('cta_text')->default('Book Appointment'),
                                Forms\Components\TextInput::make('cta_link')->default('/book'),
                                Forms\Components\TextInput::make('secondary_cta_text')->default('Our Services'),
                                Forms\Components\TextInput::make('secondary_cta_link')->default('#services'),
                                Forms\Components\TextInput::make('emergency_phone')->label('Emergency Hotline (shown below hero)')->placeholder('017XXXXXXXX'),
                                Forms\Components\TextInput::make('image_url')->label('Hero Image URL')->placeholder('https://images.unsplash.com/...'),
                            ]),

                        // 2. Text-Based Trust Bar
                        Forms\Components\Builder\Block::make('trust_bar')
                            ->label('Text-Based Trust Bar (Separated by Hospital Icons)')
                            ->schema([
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
                            ]),

                        // 3. Patient Care Journey (3 to 5 steps)
                        Forms\Components\Builder\Block::make('patient_journey')
                            ->label('Patient Care Journey (3-5 Steps)')
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Your Patient Care Journey'),
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
                            ]),

                        // 4. Services & Specialties Matrix (Cards < 8, List >= 8)
                        Forms\Components\Builder\Block::make('service_matrix')
                            ->label('Services & Specialties Matrix')
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Our Clinical Services'),
                                Forms\Components\TextInput::make('description')->default('Comprehensive healthcare solutions tailored to your needs.'),
                                Forms\Components\Repeater::make('items')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('title')->required(),
                                        Forms\Components\TextInput::make('icon')->placeholder('stethoscope, heart, activity'),
                                        Forms\Components\TextInput::make('description')->columnSpan(2),
                                    ])
                                    ->default([
                                        ['title' => 'Primary & Preventive Care', 'description' => 'Routine checkups, vaccinations, and wellness counseling.'],
                                        ['title' => 'Specialist Consultations', 'description' => 'Expert evaluation for complex medical conditions.'],
                                        ['title' => 'Diagnostic Testing & Labs', 'description' => 'Fast, accurate on-site laboratory testing.'],
                                    ]),
                            ]),

                        // 5. Doctor & Medical Team Directory (Clinic Only)
                        Forms\Components\Builder\Block::make('doctor_grid')
                            ->label('Doctor Directory (Clinic Only)')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Meet Our Specialists'),
                                Forms\Components\TextInput::make('subheadline')->default('Experienced and compassionate physicians dedicated to your health.'),
                            ]),

                        // 6. Interactive Appointment Wizard
                        Forms\Components\Builder\Block::make('appointment_wizard')
                            ->label('Interactive Appointment Wizard')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Book Your Appointment in 60 Seconds'),
                                Forms\Components\TextInput::make('subheadline')->default('Select specialty, physician, date and confirm your booking instantly.'),
                            ]),

                        // 7. Clinic Locations & Hours (Google Maps Link)
                        Forms\Components\Builder\Block::make('location_hours')
                            ->label('Clinic Locations & Hours (Google Maps Link)')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Visit Our Clinic')->columnSpan(2),
                                Forms\Components\TextInput::make('google_maps_url')->label('Google Maps Location Link')->required()->placeholder('https://maps.google.com/?q=...')->columnSpan(2),
                                Forms\Components\TextInput::make('address')->placeholder('123 Health Ave, Suite 400, Medical City'),
                                Forms\Components\TextInput::make('operating_hours')->default('Mon - Fri: 8:00 AM - 8:00 PM | Sat: 9:00 AM - 4:00 PM'),
                                Forms\Components\TextInput::make('phone')->placeholder('+1 (555) 234-5678'),
                                Forms\Components\TextInput::make('email')->placeholder('contact@clinic.com'),
                            ]),

                        // 8. Video Showcase Gallery (Max 10 videos)
                        Forms\Components\Builder\Block::make('video_gallery')
                            ->label('Video Showcase Gallery (Max 10 Videos)')
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Video Showcase & Patient Guides'),
                                Forms\Components\Repeater::make('videos')
                                    ->columns(2)
                                    ->maxItems(10)
                                    ->schema([
                                        Forms\Components\TextInput::make('title')->required(),
                                        Forms\Components\Select::make('type')
                                            ->options([
                                                'link' => 'External Social Link (YouTube / Facebook / Instagram)',
                                                'upload' => 'Direct Video Upload (< 20MB)',
                                            ])
                                            ->default('link'),
                                        Forms\Components\TextInput::make('video_url')->label('Video URL')->placeholder('https://www.youtube.com/watch?v=...'),
                                        Forms\Components\TextInput::make('thumbnail_url')->label('Custom Thumbnail Image URL')->placeholder('https://...'),
                                    ]),
                            ]),

                        // 9. Image Carousel / Slider with Configurable Aspect Ratio
                        Forms\Components\Builder\Block::make('image_carousel')
                            ->label('Image Carousel / Slider (Configurable Aspect Ratio)')
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
                                Forms\Components\Repeater::make('items')
                                    ->columns(2)
                                    ->columnSpan(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('image_url')->label('Image URL')->required()->placeholder('https://images.unsplash.com/...'),
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
                            ]),

                        // 10. Conditions Treated Library
                        Forms\Components\Builder\Block::make('condition_library')
                            ->label('Conditions Treated Library')
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Conditions We Treat'),
                                Forms\Components\Repeater::make('conditions')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\TextInput::make('description'),
                                    ])
                                    ->default([
                                        ['name' => 'Hypertension & Cardiac Health', 'description' => 'Comprehensive blood pressure monitoring and heart care.'],
                                        ['name' => 'Diabetes & Endocrine Disorders', 'description' => 'Personalized management plans for diabetes care.'],
                                    ]),
                            ]),

                        // 11. FAQ Accordion
                        Forms\Components\Builder\Block::make('faq')
                            ->label('Frequently Asked Questions')
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Frequently Asked Questions'),
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
                            ]),

                        // 12. About Practice & Facility Tour (Clinic Only)
                        Forms\Components\Builder\Block::make('about_facility')
                            ->label('About Practice & Facility Tour (Clinic Only)')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('About Our Practice & Facilities')->columnSpan(2),
                                Forms\Components\Textarea::make('mission_statement')->default('Dedicated to providing world-class medical treatment with compassion, innovation, and integrity.')->columnSpan(2),
                                Forms\Components\Repeater::make('gallery')
                                    ->columns(2)
                                    ->columnSpan(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('title')->required(),
                                        Forms\Components\TextInput::make('image_url')->required(),
                                    ]),
                            ]),

                        // 13. Healthcare Metric Band (Clinic Only)
                        Forms\Components\Builder\Block::make('stat_band')
                            ->label('Healthcare Metric Band (Clinic Only)')
                            ->schema([
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
                            ]),

                        // 14. Health Insights & Articles
                        Forms\Components\Builder\Block::make('health_insights')
                            ->label('Health Insights & Articles')
                            ->schema([
                                Forms\Components\TextInput::make('heading')->default('Latest Health Insights & Articles'),
                                Forms\Components\Repeater::make('articles')
                                    ->columns(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('title')->required(),
                                        Forms\Components\TextInput::make('image_url'),
                                        Forms\Components\TextInput::make('link'),
                                        Forms\Components\Textarea::make('excerpt')->columnSpan(2),
                                    ]),
                            ]),

                        // 15. High-Converting Action Banner
                        Forms\Components\Builder\Block::make('cta_banner')
                            ->label('High-Converting Action Banner')
                            ->columns(2)
                            ->schema([
                                Forms\Components\TextInput::make('headline')->default('Need Same-Day Care? Book Online in 60 Seconds')->columnSpan(2),
                                Forms\Components\TextInput::make('subheadline')->default('Our team is ready to provide immediate medical attention.')->columnSpan(2),
                                Forms\Components\TextInput::make('cta_text')->default('Book Your Appointment Now'),
                                Forms\Components\TextInput::make('cta_link')->default('/book'),
                            ]),

                        // 16. Privacy & Policy Text Block (Clinic Only - Technical HTML)
                        Forms\Components\Builder\Block::make('rich_text')
                            ->label('Privacy & Policy Text Block (Clinic Only - Technical HTML)')
                            ->visible(fn () => auth()->user()?->canManagePageStructure() ?? false)
                            ->schema([
                                Forms\Components\RichEditor::make('content')->required(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
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
