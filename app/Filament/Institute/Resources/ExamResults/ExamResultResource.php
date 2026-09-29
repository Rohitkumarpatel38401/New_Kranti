<?php

namespace App\Filament\Institute\Resources\ExamResults;

use App\Enums\ResultStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Shared\ResultsTable;
use App\Models\ExamResult;
use App\Services\ResultService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ExamResultResource extends Resource
{
    protected static ?string $model = ExamResult::class;

    protected static ?string $slug = 'results';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Exams';

    protected static ?string $navigationLabel = 'Results';

    protected static ?string $modelLabel = 'result';

    public static function table(Table $table): Table
    {
        return ResultsTable::configure($table)->recordActions([
            ResultsTable::viewMarksAction(),
            Action::make('submit')
                ->label('Submit to HQ')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->requiresConfirmation()
                ->modalDescription('Marks cannot be changed after submitting unless HQ returns them.')
                ->visible(fn (ExamResult $record) => $record->status === ResultStatus::Draft
                    && (bool) auth('institute')->user()?->can('submit_result'))
                ->action(function (ExamResult $record, Action $action) {
                    try {
                        app(ResultService::class)->submit($record, auth('institute')->user());
                    } catch (DomainRuleException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();
                        $action->halt();
                    }

                    Notification::make()->success()->title('Result sent to HQ')->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListExamResults::route('/')];
    }
}
