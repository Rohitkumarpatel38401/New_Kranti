<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\InstituteUser;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bell notifications in the three panels. Sent after the surrounding transaction commits,
 * so a rolled-back change never notifies anyone. Each audience is filtered by permission.
 */
final class PanelNotifier
{
    /** HQ admins who hold $permission (super admins always). */
    public static function admins(string $permission, string $title, ?string $body = null, ?string $url = null, string $icon = 'heroicon-o-bell'): void
    {
        self::afterCommit(fn () => self::send(
            Admin::query()->where('is_active', true)->get()->filter(fn (Admin $a) => $a->can($permission)),
            $title, $body, $url, $icon,
        ));
    }

    /** Active staff of one institute who hold $permission. */
    public static function institute(int $instituteId, string $permission, string $title, ?string $body = null, ?string $url = null, string $icon = 'heroicon-o-bell'): void
    {
        self::afterCommit(fn () => self::send(
            InstituteUser::query()->withoutGlobalScopes()->where('institute_id', $instituteId)->where('is_active', true)->get()
                ->filter(fn (InstituteUser $u) => $u->can($permission)),
            $title, $body, $url, $icon,
        ));
    }

    public static function student(Student $student, string $title, ?string $body = null, ?string $url = null, string $icon = 'heroicon-o-bell'): void
    {
        self::afterCommit(fn () => self::send(collect([$student]), $title, $body, $url, $icon));
    }

    private static function send(Collection $recipients, string $title, ?string $body, ?string $url, string $icon): void
    {
        if ($recipients->isEmpty()) {
            return;
        }

        $notification = Notification::make()->title($title)->body($body)->icon($icon);

        if ($url) {
            $notification->actions([Action::make('open')->label('Open')->url($url)->markAsRead()]);
        }

        $notification->sendToDatabase($recipients->values()->all());
    }

    private static function afterCommit(callable $send): void
    {
        // Runs immediately when no transaction is open.
        DB::afterCommit($send);
    }
}
