<?php

namespace App\Filament\Institute\Resources\Students;

use App\Enums\StudentStatus;
use App\Filament\Institute\Resources\Students\Pages\CreateStudent;
use App\Filament\Institute\Resources\Students\Pages\EditStudent;
use App\Filament\Institute\Resources\Students\Pages\ListStudents;
use App\Filament\Institute\Resources\Students\Pages\ViewStudent;
use App\Filament\Institute\Resources\Students\RelationManagers\DocumentsRelationManager;
use App\Filament\Shared\StudentForm;
use App\Filament\Shared\StudentInfolist;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\URL;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'registration_no';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->columnSpanFull()->tabs(StudentForm::tabs()),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return self::studentTable($table);
    }

    /** Shared with the HQ student list. */
    public static function studentTable(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('guardians'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('registration_no')->label('Reg. no.')->searchable()->copyable(),
                TextColumn::make('full_name')->label('Name')
                    ->searchable(['first_name', 'middle_name', 'last_name']),
                TextColumn::make('father')->label("Father's name")->state(fn (Student $record) => $record->fatherName()),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('joined_on')->label('Admitted')->date()->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([SelectFilter::make('status')->options(StudentStatus::class)])
            ->recordActions([ViewAction::make(), EditAction::make(), Action::make('loginAsStudent')
                ->label('Login as student')
                ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
                ->color('gray')
                ->visible(fn (Student $record): bool => (bool) auth('institute')->user()?->can('impersonate_student') && $record->can_login)
                ->url(fn (Student $record): string => URL::temporarySignedRoute(
                    'student-impersonation.start', now()->addMinutes(5), ['student' => $record, 'as' => 'institute']
                ))
                ->openUrlInNewTab()]);
    }

    public static function getRelations(): array
    {
        return [DocumentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'create' => CreateStudent::route('/create'),
            'view' => ViewStudent::route('/{record}'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }
}
