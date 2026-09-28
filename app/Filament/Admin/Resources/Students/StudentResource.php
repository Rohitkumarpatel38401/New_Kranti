<?php

namespace App\Filament\Admin\Resources\Students;

use App\Enums\AdmissionSource;
use App\Enums\StudentStatus;
use App\Filament\Admin\Resources\Students\Pages\ListStudents;
use App\Filament\Admin\Resources\Students\Pages\ViewStudent;
use App\Filament\Exports\StudentExporter;
use App\Filament\Shared\StudentInfolist;
use App\Models\Institute;
use App\Models\Student;
use BackedEnum;
use Filament\Actions\ExportAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every student in the network. HQ reads here; institutes admit and edit in their own panel.
 */
class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Students';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'registration_no';

    public static function infolist(Schema $schema): Schema
    {
        return StudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['institute', 'guardians']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('registration_no')->label('Reg. no.')->searchable()->copyable(),
                TextColumn::make('full_name')->label('Name')->searchable(['first_name', 'middle_name', 'last_name']),
                TextColumn::make('father')->label("Father's name")->state(fn (Student $record) => $record->fatherName())->toggleable(),
                TextColumn::make('network')->label('Institute')->badge()
                    ->state(fn (Student $record) => $record->institute?->is_main ? 'Main' : $record->institute?->name)
                    ->color(fn (Student $record) => $record->institute?->is_main ? 'primary' : 'gray')
                    ->description(fn (Student $record) => $record->institute?->code),
                TextColumn::make('admission_source')->label('Source')->badge()->toggleable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('joined_on')->label('Admitted')->date()->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('institute_id')->label('Institute')->searchable()
                    ->options(fn () => Institute::query()->orderBy('code')->get()->mapWithKeys(fn (Institute $i) => [$i->id => "{$i->code} — {$i->name}"])),
                SelectFilter::make('network')->label('Main / Franchise')
                    ->options(['main' => 'Main institute', 'franchise' => 'Franchises'])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $q, string $value) => $q->whereHas('institute', fn (Builder $i) => $i->where('is_main', $value === 'main')))),
                SelectFilter::make('admission_source')->label('Source')->options(AdmissionSource::class),
                SelectFilter::make('status')->options(StudentStatus::class),
            ])
            ->headerActions([ExportAction::make()->exporter(StudentExporter::class)])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'view' => ViewStudent::route('/{record}'),
        ];
    }
}
