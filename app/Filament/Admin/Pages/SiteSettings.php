<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Shared\LocalizedFields;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * Website identity, contact details, announcement bar and homepage numbers.
 * Wallet payment details live on PaymentSettings (accounts permission).
 */
class SiteSettings extends Page
{
    protected string $view = 'filament.admin.pages.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 7;

    protected static ?string $title = 'Site settings';

    /** Keys this page edits ("group.key"). */
    private const KEYS = [
        'site.name', 'site.short_name', 'site.tagline', 'site.about', 'site.established', 'site.announcement', 'site.coupon',
        'site.phone', 'site.phone_alt', 'site.whatsapp', 'site.email', 'site.address', 'site.map_embed_url', 'site.socials', 'site.stats',
        'site.online_courses_url',
    ];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('manage_website');
    }

    public function mount(): void
    {
        $settings = app(SettingsService::class);
        $state = [];

        foreach (self::KEYS as $key) {
            Arr::set($state, $key, $settings->get($key));
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Institute')->columns(2)->schema([
                TextInput::make('site.name')->label('Name')->required()->maxLength(120),
                TextInput::make('site.short_name')->label('Short name')->maxLength(20),
                ...LocalizedFields::make('site.tagline', 'Tagline', required: false),
                ...LocalizedFields::make('site.about', 'About (footer and About page)', textarea: true, required: false),
                TextInput::make('site.established')->label('Established (year)')->maxLength(4),
                TextInput::make('site.online_courses_url')->label('Online courses link')->url()->rule('url:http,https')->maxLength(255),
            ]),
            Section::make('Announcement bar')->columns(2)->schema([
                ...LocalizedFields::make('site.announcement', 'Announcement', required: false),
                TextInput::make('site.coupon')->label('Coupon code')->maxLength(30),
            ]),
            Section::make('Contact')->columns(2)->schema([
                TextInput::make('site.phone')->label('Phone')->tel()->maxLength(20),
                TextInput::make('site.phone_alt')->label('Alternate phone')->tel()->maxLength(20),
                TextInput::make('site.whatsapp')->label('WhatsApp number')->tel()->maxLength(20)
                    ->helperText('Used by the website WhatsApp buttons. Leave empty to use the main phone.'),
                TextInput::make('site.email')->label('Email')->email()->maxLength(255),
                TextInput::make('site.map_embed_url')->label('Google Maps embed URL')->url()->maxLength(500),
                Textarea::make('site.address')->label('Address')->rows(2)->columnSpanFull(),
                KeyValue::make('site.socials')->label('Social links')->keyLabel('Network (facebook, instagram, youtube…)')->valueLabel('URL')->columnSpanFull(),
            ]),
            Section::make('Homepage numbers')->columns(4)->schema([
                TextInput::make('site.stats.students')->label('Students trained'),
                TextInput::make('site.stats.courses')->label('Courses'),
                TextInput::make('site.stats.placement')->label('Placement rate'),
                TextInput::make('site.stats.years')->label('Years'),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $settings = app(SettingsService::class);

        foreach (self::KEYS as $key) {
            if (Arr::has($state, $key)) {
                $settings->set($key, Arr::get($state, $key));
            }
        }

        Notification::make()->success()->title('Settings saved')->send();
    }
}
