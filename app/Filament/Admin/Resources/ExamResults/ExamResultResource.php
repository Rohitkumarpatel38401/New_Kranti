<?php

namespace App\Filament\Admin\Resources\ExamResults;

use App\Enums\ResultStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Shared\ResultsTable;
use App\Models\ExamResult;
use App\Models\Institute;
use App\Services\ResultService;
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
use UnitEnum;

class ExamResultResource extends Resource
{
    protected static ?string $model = ExamResult::class;

    protected static ?string $slug = 'results';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Exams';

    protected static ?string $navigationLabel = 'Results';

    public static function getNavigationBadge(): ?string
    {
        $waiting = ExamResult::query()->where('status', ResultStatus::Submitted)->count();

        return $waiting > 0 ? (string) $waiting : null;
    }

    public static function table(Table $table): Table
    {
        return ResultsTable::configure($table)
            ->modifyQueryUsing(fn ($query) => $query->with('institute'))
            ->pushColumns([TextColumn::make('institute.code')->label('Institute')->description(fn (ExamResult $r) => $r->institute?->name)])
            ->pushFilters([
                SelectFilter::make('institute_id')->label('Institute')->searchable()
                    ->options(fn () => Institute::query()->orderBy('code')->get()->mapWithKeys(fn (Institute $i) => [$i->id => "{$i->code} — {$i->name}"])),
            ])
            ->recordActions([
                ResultsTable::viewMarksAction(),
                Action::make('publish')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Calculates percentage, grade, and pass/fail, and completes the enrollment.')
                    ->visible(fn (ExamResult $record) => $record->status === ResultStatus::Submitted && auth('admin')->user()?->can('review', $record))
                    ->action(fn (ExamResult $record, Action $action) => self::run($action, 'Result published',
                        fn () => app(ResultService::class)->publish($record, auth('admin')->user()))),
                Action::make('return')
                    ->label('Return for correction')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (ExamResult $record) => $record->status === ResultStatus::Submitted && auth('admin')->user()?->can('review', $record))
                    ->schema([Textarea::make('remarks')->required()->maxLength(255)])
                    ->action(fn (ExamResult $record, array $data, Action $action) => self::run($action, 'Returned to the institute',
                        fn () => app(ResultService::class)->return($record, $data['remarks'], auth('admin')->user()))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListExamResults::route('/')];
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
