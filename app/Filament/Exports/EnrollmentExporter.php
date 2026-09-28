<?php

namespace App\Filament\Exports;

use App\Models\Enrollment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class EnrollmentExporter extends Exporter
{
    protected static ?string $model = Enrollment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('enrollment_no')->label('Enrollment no.'),
            ExportColumn::make('student.registration_no')->label('Registration no.'),
            ExportColumn::make('student.full_name')->label('Student'),
            ExportColumn::make('institute.code')->label('Institute code'),
            ExportColumn::make('course.code')->label('Course'),
            ExportColumn::make('academicSession.name')->label('Session'),
            ExportColumn::make('admission_date'),
            ExportColumn::make('net_fee')->label('Course fee'),
            ExportColumn::make('hq_fee_snapshot')->label('HQ fee'),
            ExportColumn::make('status')->formatStateUsing(fn ($state) => $state?->label()),
            ExportColumn::make('approved_at'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return "Enrollment export finished: {$export->successful_rows} rows.";
    }
}
