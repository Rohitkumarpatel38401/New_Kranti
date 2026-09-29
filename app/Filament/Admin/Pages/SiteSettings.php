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
 * Website identity, contact details, announcement bar, homepage numbers, and wallet payment details.
 */
class SiteSettings extends Page
{
    protected string $view = 'filament.admin.pages.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Site settings';

    /** Keys this page edits ("group.key"). */
    private const KEYS = [
        'site.name', 'site.short_name', 'site.tagline', 'site.about', 'site.established', 'site.announcement', 'site.coupon',
        'site.phone', 'site.phone_alt', 'site.email', 'site.address', 'site.map_embed_url', 'site.socials', 'site.stats',
        'site.online_courses_url',
        'payment.upi_id', 'payment.account_name', 'payment.account_number', 'payment.ifsc', 'payment.bank_name',
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
                TextInput::make('site.online_courses_url')->label('Online courses link')->url()->maxLength(255),
            ]),
            Section::make('Announcement bar')->columns(2)->schema([
                ...LocalizedFields::make('site.announcement', 'Announcement', required: false),
                TextInput::make('site.coupon')->label('Coupon code')->maxLength(30),
            ]),
            Section::make('Contact')->columns(2)->schema([
                TextInput::make('site.phone')->label('Phone')->tel()->maxLength(20),
                TextInput::make('site.phone_alt')->label('Alternate phone')->tel()->maxLength(20),
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
            Section::make('Wallet recharge payment details (shown to franchises)')->columns(2)->schema([
                TextInput::make('payment.upi_id')->label('UPI ID')->maxLength(100),
                TextInput::make('payment.account_name')->label('Account name')->maxLength(120),
                TextInput::make('payment.account_number')->label('Account number')->maxLength(30),
                TextInput::make('payment.ifsc')->label('IFSC')->maxLength(20),
                TextInput::make('payment.bank_name')->label('Bank')->maxLength(120),
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
