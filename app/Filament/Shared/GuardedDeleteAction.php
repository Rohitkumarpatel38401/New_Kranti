<?php

namespace App\Filament\Shared;

use App\Exceptions\DomainRuleException;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Delete action that shows a business-rule refusal (e.g. "still in use") as a notification instead of an error page.
 */
class GuardedDeleteAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()->action(function (Model $record, DeleteAction $action): void {
            try {
                $record->delete();
            } catch (DomainRuleException $e) {
                Notification::make()->danger()->title($e->getMessage())->send();
                $action->halt();
            }

            $action->success();
        });
    }
}
