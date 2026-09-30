<?php

namespace App\Filament\Student\Resources\MyEnrollments;

use App\Enums\EnrollmentStatus;
use App\Filament\Student\Resources\MyEnrollments\Pages\ListMyEnrollments;
use App\Filament\Student\Resources\MyEnrollments\Pages\ViewMyEnrollment;
use App\Models\Enrollment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The logged-in student's approved enrollments (read-only). Other students' records never match the query,
 * so their URLs are 404.
 */
class MyEnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'my-courses';

    public static function getNavigationLabel(): string
    {
        return __('portal.enrollments.title');
    }

    public static function getModelLabel(): string
    {
        return __('portal.enrollments.single');
    }

    public static function getPluralModelLabel(): string
    {
        return __('portal.enrollments.title');
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
        return parent::getEloquentQuery()
            ->where('student_id', auth('student')->id())
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->with(['course', 'institute', 'academicSession']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('admission_date', 'desc')
            ->columns([
                TextColumn::make('enrollment_no')->label(__('portal.enrollments.no')),
                TextColumn::make('course.name')->label(__('portal.enrollments.course'))
                    ->state(fn (Enrollment $r) => $r->course?->code.' — '.$r->course?->localized('name'))->wrap(),
                TextColumn::make('institute.name')->label(__('portal.enrollments.centre')),
                TextColumn::make('academicSession.name')->label(__('portal.enrollments.session')),
                TextColumn::make('admission_date')->label(__('portal.enrollments.admitted'))->date('d M Y'),
                TextColumn::make('status')->label(__('portal.enrollments.status'))->badge(),
            ])
            ->recordUrl(fn (Enrollment $r) => ViewMyEnrollment::getUrl(['record' => $r]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyEnrollments::route('/'),
            'view' => ViewMyEnrollment::route('/{record}'),
        ];
    }
}
