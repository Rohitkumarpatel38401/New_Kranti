<?php

namespace App\Filament\Student\Pages;

use App\Enums\AddressType;
use App\Exceptions\DomainRuleException;
use App\Services\StudentAccountService;
use App\Services\StudentImpersonationService;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The student's single account page: read-only profile (no Aadhaar) and the password form.
 * The forced first-login password change (EnsureStudentPasswordChanged) sends students here.
 */
class MyProfile extends Page
{
    protected string $view = 'filament.student.pages.my-profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'my-profile';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('portal.profile.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('portal.profile.title');
    }

    public function getSubheading(): ?string
    {
        return auth('student')->user()?->must_change_password && ! $this->impersonating()
            ? __('portal.password.first_login')
            : null;
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

    public function changePassword(): void
    {
        // Staff viewing the portal as the student never change the student's password.
        abort_if($this->impersonating(), 403);

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

    protected function getViewData(): array
    {
        $student = auth('student')->user()->load(['personal', 'guardians', 'institute', 'addresses']);
        $photo = $student->photo_path && Storage::disk('local')->exists($student->photo_path)
            ? 'data:'.Storage::disk('local')->mimeType($student->photo_path).';base64,'.base64_encode(Storage::disk('local')->get($student->photo_path))
            : null;

        return [
            'student' => $student,
            'photo' => $photo,
            'address' => $student->address(AddressType::Permanent),
            'impersonating' => $this->impersonating(),
        ];
    }

    private function impersonating(): bool
    {
        return app(StudentImpersonationService::class)->isImpersonating();
    }
}
