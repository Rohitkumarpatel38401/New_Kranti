<?php

namespace App\Filament\Admin\Resources\WalletRechargeRequests;

use App\Enums\RechargeStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\WalletRechargeRequests\Pages\ListWalletRechargeRequests;
use App\Models\Institute;
use App\Models\WalletRechargeRequest;
use App\Services\WalletRechargeService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class WalletRechargeRequestResource extends Resource
{
    protected static ?string $model = WalletRechargeRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Wallets';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Recharge requests';

    public static function getNavigationBadge(): ?string
    {
        $pending = WalletRechargeRequest::query()->where('status', RechargeStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['institute', 'requestedBy']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('request_no')->searchable(),
                TextColumn::make('institute.code')->label('Institute')->searchable()
                    ->description(fn (WalletRechargeRequest $r) => $r->institute?->name),
                TextColumn::make('amount')->money('INR')->sortable(),
                TextColumn::make('payment_mode')->badge(),
                TextColumn::make('utr_reference')->label('UTR')->searchable()->copyable(),
                TextColumn::make('paid_on')->date(),
                TextColumn::make('status')->badge()->color(fn (RechargeStatus $state) => match ($state) {
                    RechargeStatus::Approved => 'success',
                    RechargeStatus::Rejected => 'danger',
                    RechargeStatus::Pending => 'warning',
                }),
                TextColumn::make('reviewedBy.name')->label('Reviewed by')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(RechargeStatus::class)->default(RechargeStatus::Pending->value),
                SelectFilter::make('institute_id')->label('Institute')
                    ->options(fn () => Institute::query()->orderBy('code')->pluck('name', 'id'))->searchable(),
            ])
            ->recordActions([
                Action::make('proof')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->visible(fn (WalletRechargeRequest $r) => filled($r->proof_path))
                    ->action(fn (WalletRechargeRequest $r) => Storage::disk($r->proof_disk)->download($r->proof_path)),
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (WalletRechargeRequest $r) => "Credit ₹{$r->amount} to {$r->institute?->name}? Check the UTR against the bank statement first.")
                    ->visible(fn (WalletRechargeRequest $r) => $r->status === RechargeStatus::Pending && auth('admin')->user()?->can('review', $r))
                    ->action(fn (WalletRechargeRequest $r, Action $action) => self::run(
                        fn () => app(WalletRechargeService::class)->approve($r, auth('admin')->user()), 'Wallet credited', $action)),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (WalletRechargeRequest $r) => $r->status === RechargeStatus::Pending && auth('admin')->user()?->can('review', $r))
                    ->schema([Textarea::make('remarks')->required()->maxLength(255)])
                    ->action(fn (WalletRechargeRequest $r, array $data, Action $action) => self::run(
                        fn () => app(WalletRechargeService::class)->reject($r, $data['remarks'], auth('admin')->user()), 'Request rejected', $action)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWalletRechargeRequests::route('/')];
    }

    private static function run(Closure $work, string $success, Action $action): void
    {
        try {
            $work();
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title($success)->send();
    }
}
