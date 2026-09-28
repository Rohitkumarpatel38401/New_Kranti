<?php

namespace App\Filament\Exports;

use App\Models\Student;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StudentExporter extends Exporter
{
    protected static ?string $model = Student::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('registration_no')->label('Registration no.'),
            ExportColumn::make('full_name')->label('Name'),
            ExportColumn::make('father')->label("Father's name")->state(fn (Student $record) => $record->fatherName()),
            ExportColumn::make('institute.code')->label('Institute code'),
            ExportColumn::make('institute.name')->label('Institute'),
            ExportColumn::make('admission_source')->label('Source')->formatStateUsing(fn ($state) => $state?->label()),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('joined_on')->label('Admitted on'),
            ExportColumn::make('status')->formatStateUsing(fn ($state) => $state?->label()),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return "Student export finished: {$export->successful_rows} rows.";
    }
}
