<?php

namespace App\Filament\Admin\Resources\FranchiseApplications;

use App\Enums\AddressType;
use App\Enums\FranchiseApplicationStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\FranchiseApplications\Pages\ListFranchiseApplications;
use App\Filament\Admin\Resources\FranchiseApplications\Pages\ViewFranchiseApplication;
use App\Filament\Admin\Resources\Institutes\InstituteResource;
use App\Models\FranchiseApplication;
use App\Services\FranchiseOnboardingService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class FranchiseApplicationResource extends Resource
{
    protected static ?string $model = FranchiseApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Franchise Network';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'application_no';

    public static function getNavigationBadge(): ?string
    {
        $open = FranchiseApplication::query()->where('status', FranchiseApplicationStatus::New)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Application')->columns(3)->schema([
                TextEntry::make('application_no'),
                TextEntry::make('status')->badge(),
                TextEntry::make('created_at')->label('Received')->dateTime(),
                TextEntry::make('applicant_name'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('proposed_center_name'),
                TextEntry::make('computers_count')->placeholder('—'),
                TextEntry::make('area_sqft')->label('Area (sq ft)')->placeholder('—'),
                TextEntry::make('experience_years')->placeholder('—'),
                TextEntry::make('address')->state(fn (FranchiseApplication $record) => $record->address(AddressType::Office)?->oneLine())
                    ->placeholder('—')->columnSpan(2),
                TextEntry::make('message')->placeholder('—')->columnSpanFull(),
                TextEntry::make('reviewedBy.name')->label('Reviewed by')->placeholder('—'),
                TextEntry::make('review_remarks')->placeholder('—'),
                TextEntry::make('convertedInstitute.code')->label('Institute')->placeholder('—')
                    ->url(fn (FranchiseApplication $record) => $record->converted_institute_id
                        ? InstituteResource::getUrl('view', ['record' => $record->converted_institute_id]) : null),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('addresses'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('application_no')->searchable(),
                TextColumn::make('proposed_center_name')->label('Centre')->searchable(),
                TextColumn::make('applicant_name')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('city')->state(fn (FranchiseApplication $record) => $record->address(AddressType::Office)?->city),
                TextColumn::make('status')->badge()->color(fn (FranchiseApplicationStatus $state) => match ($state) {
                    FranchiseApplicationStatus::New => 'warning',
                    FranchiseApplicationStatus::Contacted => 'info',
                    FranchiseApplicationStatus::Approved => 'success',
                    FranchiseApplicationStatus::Rejected => 'danger',
                }),
                TextColumn::make('created_at')->label('Received')->since(),
            ])
            ->filters([SelectFilter::make('status')->options(FranchiseApplicationStatus::class)])
            ->recordActions([
                ViewAction::make(),
                Action::make('contacted')
                    ->label('Mark contacted')
                    ->icon(Heroicon::OutlinedPhone)
                    ->visible(fn (FranchiseApplication $record) => $record->status === FranchiseApplicationStatus::New && self::canReview())
                    ->action(fn (FranchiseApplication $record) => self::run(fn () => app(FranchiseOnboardingService::class)
                        ->markContacted($record, auth('admin')->user()), 'Marked as contacted')),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (FranchiseApplication $record) => $record->status->isOpen() && self::canReview())
                    ->schema([Textarea::make('remarks')->required()->maxLength(255)])
                    ->action(fn (FranchiseApplication $record, array $data) => self::run(fn () => app(FranchiseOnboardingService::class)
                        ->reject($record, $data['remarks'], auth('admin')->user()), 'Application rejected')),
                Action::make('convert')
                    ->label('Convert to institute')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->color('success')
                    ->visible(fn (FranchiseApplication $record) => $record->status->isOpen() && self::canReview())
                    ->fillForm(fn (FranchiseApplication $record) => [
                        'institute_name' => $record->proposed_center_name,
                        'owner_name' => $record->applicant_name,
                        'owner_email' => $record->email,
                        'owner_phone' => $record->phone,
                    ])
                    ->schema([
                        TextInput::make('institute_name')->required()->maxLength(255),
                        TextInput::make('owner_name')->required()->maxLength(255),
                        TextInput::make('owner_email')->label('Owner login email')->required()->email(),
                        TextInput::make('owner_phone')->required()->tel()->maxLength(20),
                    ])
                    ->modalDescription('Creates the institute and emails the owner a login with a temporary password.')
                    ->action(function (FranchiseApplication $record, array $data, Action $action) {
                        self::run(fn () => app(FranchiseOnboardingService::class)->convert($record, auth('admin')->user(), $data),
                            'Institute created and owner login emailed', $action);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFranchiseApplications::route('/'),
            'view' => ViewFranchiseApplication::route('/{record}'),
        ];
    }

    private static function canReview(): bool
    {
        return (bool) auth('admin')->user()?->can('approve_franchise');
    }

    private static function run(Closure $work, string $success, ?Action $action = null): void
    {
        try {
            $work();
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action?->halt();

            return;
        }

        Notification::make()->success()->title($success)->send();
    }
}
