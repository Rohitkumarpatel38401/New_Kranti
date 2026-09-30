<?php

namespace App\Filament\Institute\Resources\WalletRecharges;

use App\Enums\PaymentMode;
use App\Enums\RechargeStatus;
use App\Filament\Institute\Resources\WalletRecharges\Pages\CreateWalletRecharge;
use App\Filament\Institute\Resources\WalletRecharges\Pages\ListWalletRecharges;
use App\Models\WalletRechargeRequest;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class WalletRechargeResource extends Resource
{
    protected static ?string $model = WalletRechargeRequest::class;

    protected static ?string $slug = 'wallet-recharges';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Wallet';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Recharge requests';

    protected static ?string $modelLabel = 'recharge request';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Pay HQ first')
                ->description(self::paymentInstructions())
                ->columns(2)
                ->schema([
                    TextInput::make('amount')->label('Amount paid (₹)')->required()->numeric()->minValue(1)->maxValue(10000000),
                    Select::make('payment_mode')->options(PaymentMode::class)->required()->default(PaymentMode::Upi),
                    TextInput::make('utr_reference')->label('UTR / reference no.')->required()->maxLength(60),
                    DatePicker::make('paid_on')->required()->maxDate(now())->default(now()),
                    FileUpload::make('proof_path')
                        ->label('Payment screenshot / receipt')
                        ->disk('local')
                        ->visibility('private')
                        ->directory(fn () => 'wallet-proofs/'.auth('institute')->user()?->institute_id)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                        ->maxSize(5120)
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_no')->searchable(),
                TextColumn::make('amount')->money('INR'),
                TextColumn::make('payment_mode')->badge(),
                TextColumn::make('utr_reference')->label('UTR')->searchable(),
                TextColumn::make('paid_on')->date(),
                TextColumn::make('status')->badge()->color(fn (RechargeStatus $state) => match ($state) {
                    RechargeStatus::Approved => 'success',
                    RechargeStatus::Rejected => 'danger',
                    RechargeStatus::Pending => 'warning',
                }),
                TextColumn::make('admin_remarks')->label('HQ remarks')->placeholder('—')->wrap(),
            ]);
    }

    public static function paymentInstructions(): string
    {
        // Admin → Site settings wins; .env (config/kcti.php) is the fallback.
        $saved = app(SettingsService::class)->all()['payment'] ?? [];
        $p = array_merge(config('kcti.payment'), array_filter($saved, fn ($v) => filled($v)));

        return collect([
            $p['upi_id'] ? "UPI: {$p['upi_id']}" : null,
            $p['account_number'] ? "Bank: {$p['account_name']}, A/c {$p['account_number']}, IFSC {$p['ifsc']} ({$p['bank_name']})" : null,
        ])->filter()->implode(' · ') ?: 'Pay Kranti Computer Training Institute HQ using the account details shared with you, then submit the proof below.';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletRecharges::route('/'),
            'create' => CreateWalletRecharge::route('/create'),
        ];
    }
}
