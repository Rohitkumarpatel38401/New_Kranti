<?php

namespace App\Filament\Student\Pages;

use App\Exceptions\DomainRuleException;
use App\Services\StudentAccountService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * Change the portal password. Forced on first login (EnsureStudentPasswordChanged); available any time after.
 */
class ChangePassword extends Page
{
    protected string $view = 'filament.student.pages.change-password';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?int $navigationSort = 90;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('portal.password.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('portal.password.title');
    }

    public function getSubheading(): ?string
    {
        return auth('student')->user()?->must_change_password ? __('portal.password.first_login') : null;
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            TextInput::make('current_password')->label(__('portal.password.current'))->password()->revealable()->required()->autocomplete('current-password'),
            TextInput::make('password')->label(__('portal.password.new'))->password()->revealable()->required()
                ->minLength(StudentAccountService::MIN_LENGTH)->confirmed()->autocomplete('new-password'),
            TextInput::make('password_confirmation')->label(__('portal.password.confirm'))->password()->revealable()->required()->autocomplete('new-password'),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            app(StudentAccountService::class)->changePassword(auth('student')->user(), $data['current_password'], $data['password']);
        } catch (DomainRuleException $e) {
            throw ValidationException::withMessages(['data.password' => $e->getMessage()]);
        }

        // The password hash changed: keep this session valid (AuthenticateSession compares it).
        session()->put('password_hash_'.Filament::getAuthGuard(), auth('student')->user()->getAuthPassword());

        Notification::make()->success()->title(__('portal.password.changed'))->send();
        $this->redirect(Dashboard::getUrl(panel: 'student'));
    }
}
