<?php

namespace App\Filament\Admin\Resources\Institutes;

use App\Enums\AddressType;
use App\Enums\InstituteStatus;
use App\Enums\WalletSource;
use App\Enums\WalletTransactionType;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Institutes\Pages\CreateInstitute;
use App\Filament\Admin\Resources\Institutes\Pages\EditInstitute;
use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Filament\Admin\Resources\Institutes\Pages\ViewInstitute;
use App\Filament\Admin\Resources\Institutes\RelationManagers\CoursesRelationManager;
use App\Filament\Admin\Resources\Institutes\RelationManagers\DocumentsRelationManager;
use App\Filament\Admin\Resources\Institutes\RelationManagers\StatusHistoriesRelationManager;
use App\Filament\Admin\Resources\Institutes\RelationManagers\UsersRelationManager;
use App\Filament\Shared\AddressFields;
use App\Filament\Shared\LocalizedFields;
use App\Models\Address;
use App\Models\Institute;
use App\Services\ImpersonationService;
use App\Services\InstituteService;
use App\Services\WalletService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;
use UnitEnum;

class InstituteResource extends Resource
{
    protected static ?string $model = Institute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Franchise Network';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Institute')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(20),
                TextInput::make('alt_phone')->label('Alternate phone')->tel()->maxLength(20),
                TextInput::make('website')->url()->maxLength(255),
                TextInput::make('registration_no')->label('Registration no.')->maxLength(100)->unique(ignoreRecord: true),
                TextInput::make('gst_no')->label('GST no.')->maxLength(20)->unique(ignoreRecord: true),
                TextInput::make('pan_no')->label('PAN')->maxLength(20),
                DatePicker::make('established_on'),
                DatePicker::make('franchise_start_on'),
                DatePicker::make('franchise_expiry_on')->afterOrEqual('franchise_start_on'),
            ]),
            Section::make('Owner & centre')->columns(2)->schema([
                TextInput::make('profile.owner_name')->label('Owner name')->maxLength(255),
                TextInput::make('profile.owner_phone')->label('Owner phone')->tel()->maxLength(20),
                TextInput::make('profile.owner_email')->label('Owner email')->email()->maxLength(255),
                TextInput::make('profile.owner_qualification')->label('Owner qualification')->maxLength(255),
                TextInput::make('profile.area_sqft')->label('Area (sq ft)')->integer()->minValue(0),
                TextInput::make('profile.computers_count')->label('Computers')->integer()->minValue(0),
                TextInput::make('profile.classrooms_count')->label('Classrooms')->integer()->minValue(0),
                TimePicker::make('profile.opening_time')->label('Opens at')->seconds(false),
                TimePicker::make('profile.closing_time')->label('Closes at')->seconds(false),
                ...LocalizedFields::make('profile.about', 'About', textarea: true, required: false),
            ]),
            Section::make('Office address')->columns(2)->schema(AddressFields::make('office_address')),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Institute')->columns(3)->schema([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('status')->badge()->formatStateUsing(fn (InstituteStatus $state) => $state->label())
                    ->color(fn (InstituteStatus $state) => self::statusColor($state)),
                IconEntry::make('is_main')->label('Main institute')->boolean(),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('owner.name')->label('Owner login')->placeholder('No owner yet'),
                TextEntry::make('profile.owner_name')->label('Owner name')->placeholder('—'),
                TextEntry::make('office_address')->label('Office address')->placeholder('—')
                    ->state(fn (Institute $record): ?string => $record->address(AddressType::Office)?->oneLine()),
                TextEntry::make('franchise_start_on')->date()->placeholder('—'),
                TextEntry::make('franchise_expiry_on')->date()->placeholder('—'),
                TextEntry::make('approved_at')->dateTime()->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['addresses', 'owner']))
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('is_main')->label('Main')->boolean(),
                TextColumn::make('city')->label('City')
                    ->state(fn (Institute $record): ?string => $record->address(AddressType::Office)?->city),
                TextColumn::make('owner.name')->label('Owner')->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (InstituteStatus $state) => $state->label())
                    ->color(fn (InstituteStatus $state) => self::statusColor($state)),
                TextColumn::make('phone')->toggleable(),
                TextColumn::make('email')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(InstituteStatus::class),
                SelectFilter::make('state')
                    ->options(fn () => Address::query()->where('addressable_type', (new Institute)->getMorphClass())
                        ->distinct()->orderBy('state')->pluck('state', 'state')->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $query, string $state) => $query->whereHas('addresses', fn (Builder $q) => $q->where('state', $state)))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('changeStatus')
                    ->label('Change status')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (Institute $record): bool => ! $record->is_main
                        && (bool) auth('admin')->user()?->can('suspend_institute'))
                    ->schema([
                        Select::make('status')->required()
                            ->options(fn (Institute $record) => collect(InstituteStatus::cases())
                                ->reject(fn (InstituteStatus $case) => $case === $record->status)
                                ->mapWithKeys(fn (InstituteStatus $case) => [$case->value => $case->label()])),
                        Textarea::make('reason')->required()->maxLength(255),
                    ])
                    ->action(function (Institute $record, array $data, Action $action): void {
                        try {
                            app(InstituteService::class)->changeStatus(
                                $record, InstituteStatus::from($data['status']), $data['reason'], auth('admin')->user(),
                            );
                        } catch (DomainRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title('Status updated')->send();
                    }),
                Action::make('adjustWallet')
                    ->label('Adjust wallet')
                    ->icon(Heroicon::OutlinedWallet)
                    ->color('gray')
                    ->visible(fn (): bool => (bool) auth('admin')->user()?->can('adjust_wallet'))
                    ->modalDescription(fn (Institute $record) => 'Current balance: ₹'.app(WalletService::class)->balance($record))
                    ->schema([
                        Select::make('type')->options(WalletTransactionType::class)->required(),
                        TextInput::make('amount')->label('Amount (₹)')->required()->numeric()->minValue(0.01),
                        Textarea::make('reason')->required()->maxLength(255),
                    ])
                    ->action(function (Institute $record, array $data, Action $action): void {
                        $wallets = app(WalletService::class);
                        $amount = number_format((float) $data['amount'], 2, '.', '');
                        $reason = 'Adjustment: '.$data['reason'];

                        try {
                            $type = $data['type'] instanceof WalletTransactionType ? $data['type'] : WalletTransactionType::from($data['type']);
                            $type === WalletTransactionType::Credit
                                ? $wallets->credit($record, $amount, WalletSource::Adjustment, null, $reason, auth('admin')->user())
                                : $wallets->debit($record, $amount, WalletSource::Adjustment, null, $reason, auth('admin')->user());
                        } catch (DomainRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title('Wallet adjusted')->send();
                    }),
                Action::make('impersonate')
                    ->label('Login as Institute')
                    ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                    ->color('gray')
                    ->visible(fn (Institute $record): bool => (bool) auth('admin')->user()?->can('impersonate_institute')
                        && $record->isActive()
                        && app(ImpersonationService::class)->ownerOf($record) !== null)
                    ->url(fn (Institute $record): string => URL::temporarySignedRoute(
                        'impersonation.start', now()->addMinutes(5), ['institute' => $record]
                    ))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
            CoursesRelationManager::class,
            DocumentsRelationManager::class,
            StatusHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstitutes::route('/'),
            'create' => CreateInstitute::route('/create'),
            'view' => ViewInstitute::route('/{record}'),
            'edit' => EditInstitute::route('/{record}/edit'),
        ];
    }

    public static function statusColor(InstituteStatus $status): string
    {
        return match ($status) {
            InstituteStatus::Active => 'success',
            InstituteStatus::Pending => 'warning',
            default => 'danger',
        };
    }
}
