<?php

namespace App\Filament\Student\Resources\MyPayments;

use App\Enums\FeePaymentStatus;
use App\Filament\Student\Resources\MyPayments\Pages\ListMyPayments;
use App\Models\FeePayment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The logged-in student's fee receipts (read-only). Cancelled receipts stay listed, marked cancelled.
 */
class MyPaymentResource extends Resource
{
    protected static ?string $model = FeePayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'my-receipts';

    public static function getNavigationLabel(): string
    {
        return __('portal.fees.receipts');
    }

    public static function getPluralModelLabel(): string
    {
        return __('portal.fees.receipts');
    }

    public static function canViewAny(): bool
    {
        return auth('student')->check();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('student_id', auth('student')->id())->with('enrollment.course');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_on', 'desc')
            ->columns([
                TextColumn::make('receipt_no')->label(__('portal.fees.receipt_no')),
                TextColumn::make('paid_on')->label(__('portal.fees.date'))->date('d M Y'),
                TextColumn::make('enrollment.course.code')->label(__('portal.enrollments.course')),
                TextColumn::make('amount')->label(__('portal.fees.amount'))->money('INR'),
                TextColumn::make('mode')->label(__('portal.fees.mode'))->badge(),
                TextColumn::make('status')->label(__('portal.enrollments.status'))->badge()
                    ->color(fn (FeePaymentStatus $state) => $state === FeePaymentStatus::Valid ? 'success' : 'danger'),
            ])
            ->recordActions([
                Action::make('receipt')->label(__('portal.fees.view_receipt'))->icon(Heroicon::OutlinedPrinter)
                    ->url(fn (FeePayment $r) => route('receipts.show', $r), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMyPayments::route('/')];
    }
}
