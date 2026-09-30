<?php

namespace App\Filament\Admin\Pages;

use App\Services\SettingsService;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * The bank / UPI details franchises pay wallet recharges into. Money-sensitive: only admins who
 * approve recharges may change them, and every change is activity-logged with old and new values.
 */
class PaymentSettings extends Page
{
    protected string $view = 'filament.admin.pages.site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Wallets';

    protected static ?int $navigationSort = 99;

    protected static ?string $title = 'Payment details';

    private const KEYS = ['payment.upi_id', 'payment.account_name', 'payment.account_number', 'payment.ifsc', 'payment.bank_name'];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth('admin')->user()?->can('approve_wallet_recharge');
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
            Section::make('Wallet recharge payment details (shown to franchises)')
                ->description('Changes are logged. Double-check the account before saving: franchises send money here.')
                ->columns(2)->schema([
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

        Notification::make()->success()->title('Payment details saved')->send();
    }
}
