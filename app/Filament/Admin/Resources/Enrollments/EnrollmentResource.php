<?php

namespace App\Filament\Admin\Resources\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Exports\EnrollmentExporter;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Services\EnrollmentService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Admissions';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $pending = Enrollment::query()->where('status', EnrollmentStatus::PendingApproval)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['student', 'course', 'institute', 'academicSession']))
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('enrollment_no')->label('Enrollment no.')->placeholder('—')->searchable(),
                TextColumn::make('student.registration_no')->label('Reg. no.')->searchable(),
                TextColumn::make('student.first_name')->label('Student')
                    ->formatStateUsing(fn (Enrollment $record) => $record->student?->full_name),
                TextColumn::make('institute.name')->label('Institute')->badge()
                    ->formatStateUsing(fn (Enrollment $record) => $record->institute?->is_main ? 'Main' : $record->institute?->name)
                    ->color(fn (Enrollment $record) => $record->institute?->is_main ? 'primary' : 'gray'),
                TextColumn::make('course.code')->label('Course'),
                TextColumn::make('academicSession.name')->label('Session')->toggleable(),
                TextColumn::make('hq_fee_snapshot')->label('HQ fee')->money('INR')->placeholder('—'),
                TextColumn::make('submitted_at')->label('Submitted')->since()->placeholder('—'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(EnrollmentStatus::class),
                SelectFilter::make('institute_id')->label('Institute')->searchable()
                    ->options(fn () => Institute::query()->orderBy('code')->get()->mapWithKeys(fn (Institute $i) => [$i->id => "{$i->code} — {$i->name}"])),
                SelectFilter::make('network')->label('Main / Franchise')
                    ->options(['main' => 'Main institute', 'franchise' => 'Franchises'])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $q, string $value) => $q->whereHas('institute', fn (Builder $i) => $i->where('is_main', $value === 'main')))),
                SelectFilter::make('course_id')->label('Course')
                    ->options(fn () => Course::query()->orderBy('code')->pluck('code', 'id')),
            ])
            ->headerActions([ExportAction::make()->exporter(EnrollmentExporter::class)])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Issues the enrollment number and opens the student portal login (password: date of birth, DDMMYYYY).')
                    ->visible(fn (Enrollment $record) => $record->status === EnrollmentStatus::PendingApproval && auth('admin')->user()?->can('review', $record))
                    ->action(fn (Enrollment $record, Action $action) => self::run($action, 'Enrollment approved',
                        fn () => app(EnrollmentService::class)->approve($record, auth('admin')->user()))),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Enrollment $record) => $record->status === EnrollmentStatus::PendingApproval && auth('admin')->user()?->can('review', $record))
                    ->modalDescription('The HQ fee is refunded to the institute wallet.')
                    ->schema([Textarea::make('reason')->required()->maxLength(255)])
                    ->action(fn (Enrollment $record, array $data, Action $action) => self::run($action, 'Enrollment rejected and refunded',
                        fn () => app(EnrollmentService::class)->reject($record, $data['reason'], auth('admin')->user()))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEnrollments::route('/')];
    }

    private static function run(Action $action, string $success, Closure $work): void
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
