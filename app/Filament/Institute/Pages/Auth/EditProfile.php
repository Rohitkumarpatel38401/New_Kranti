<?php

namespace App\Filament\Institute\Pages\Auth;

use App\Services\ImpersonationService;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * An impersonating HQ admin may fix the owner's name or phone, but never their email or password.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        $impersonating = app(ImpersonationService::class)->isImpersonating();

        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent()->disabled($impersonating),
            TextInput::make('phone')->tel()->maxLength(20),
            $this->getPasswordFormComponent()->hidden($impersonating),
            $this->getPasswordConfirmationFormComponent()->hidden($impersonating),
            $this->getCurrentPasswordFormComponent()->hidden($impersonating),
        ]);
    }
}
