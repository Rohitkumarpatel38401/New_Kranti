<?php

namespace App\Filament\Student\Pages\Auth;

use App\Support\DemoLogin;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Students sign in with their registration number or their email.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        // On local the form starts filled with the demo student.
        if ($demo = DemoLogin::credentials('student')) {
            $this->form->fill(['login' => $demo['login'], 'password' => $demo['password'], 'remember' => false]);
        }
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label(__('auth_kcti.student_login_label'))
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            ...static::normalizeLogin($data['login']),
            'password' => $data['password'],
        ];
    }

    public static function normalizeLogin(string $value): array
    {
        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['email' => Str::lower($value)];
        }

        return ['registration_no' => Str::upper($value)];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
