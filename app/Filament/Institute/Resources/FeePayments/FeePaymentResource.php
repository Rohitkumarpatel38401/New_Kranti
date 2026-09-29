<?php

namespace App\Filament\Institute\Resources\FeePayments;

use App\Enums\FeePaymentStatus;
use App\Enums\PaymentMode;
use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\FeePayments\Pages\ListFeePayments;
use App\Models\FeePayment;
use App\Services\FeeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use UnitEnum;

class FeePaymentResource extends Resource
{
    protected static ?string $model = FeePayment::class;

    protected static ?string $slug = 'fee-receipts';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Fees';

    protected static ?string $navigationLabel = 'Receipts';

    protected static ?string $modelLabel = 'receipt';

    public static function table(Table $table): Table
    {
        return self::receiptsTable($table, fn () => auth('institute')->user());
    }

    /** Shared with the HQ receipts list; $actor resolves who is acting (institute user or admin). */
    public static function receiptsTable(Table $table, \Closure $actor): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student', 'enrollment.course', 'receivedBy', 'receivedByAdmin']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('receipt_no')->searchable()->copyable(),
                TextColumn::make('paid_on')->date()->sortable(),
                TextColumn::make('student.registration_no')->label('Reg. no.')->searchable(),
                TextColumn::make('student.first_name')->label('Student')
                    ->formatStateUsing(fn (FeePayment $record) => $record->student?->full_name),
                TextColumn::make('enrollment.course.code')->label('Course'),
                TextColumn::make('amount')->money('INR')->sortable()
                    // Cancelled receipts never count towards collections.
                    ->summarize(Sum::make()->label('Collected (valid receipts)')->money('INR')
                        ->query(fn (QueryBuilder $query) => $query->where('status', FeePaymentStatus::Valid->value))),
                TextColumn::make('mode')->badge(),
                TextColumn::make('status')->badge()->color(fn (FeePaymentStatus $state) => $state === FeePaymentStatus::Valid ? 'success' : 'danger'),
                TextColumn::make('received_by')->label('Received by')->toggleable()
                    ->state(fn (FeePayment $record) => $record->receivedBy?->name ?? $record->receivedByAdmin?->name),
                TextColumn::make('cancel_reason')->label('Cancel reason')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(FeePaymentStatus::class),
                SelectFilter::make('mode')->options(PaymentMode::class),
                Filter::make('paid_between')->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('paid_on', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('paid_on', '<=', $d))),
            ])
            ->recordActions([
                Action::make('print')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (FeePayment $record) => route('receipts.show', $record), shouldOpenInNewTab: true),
                Action::make('cancel')
                    ->label('Cancel receipt')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (FeePayment $record) => $record->isValid() && (bool) $actor()?->can('cancel', $record))
                    ->modalDescription('The amount becomes due again. The receipt stays on record as cancelled.')
                    ->schema([Textarea::make('reason')->required()->maxLength(255)])
                    ->action(function (FeePayment $record, array $data, Action $action) use ($actor) {
                        try {
                            app(FeeService::class)->cancel($record, $data['reason'], $actor());
                        } catch (DomainRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title("Receipt {$record->receipt_no} cancelled")->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFeePayments::route('/')];
    }
}
