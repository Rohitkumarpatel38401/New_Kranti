<?php

namespace App\Filament\Exports;

use App\Models\FeePayment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class FeePaymentExporter extends Exporter
{
    protected static ?string $model = FeePayment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('receipt_no'),
            ExportColumn::make('paid_on'),
            ExportColumn::make('institute.code')->label('Institute code'),
            ExportColumn::make('institute.name')->label('Institute'),
            ExportColumn::make('student.registration_no')->label('Registration no.'),
            ExportColumn::make('student.full_name')->label('Student'),
            ExportColumn::make('enrollment.course.code')->label('Course'),
            ExportColumn::make('amount'),
            ExportColumn::make('mode')->formatStateUsing(fn ($state) => $state?->label()),
            ExportColumn::make('reference_no'),
            ExportColumn::make('status')->formatStateUsing(fn ($state) => $state?->label()),
            ExportColumn::make('cancel_reason'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return "Fee receipt export finished: {$export->successful_rows} rows.";
    }
}
