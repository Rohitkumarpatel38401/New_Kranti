<?php

namespace App\Filament\Institute\Pages\Auth;

use App\Support\DemoLogin;
use Filament\Auth\Pages\Login as BaseLogin;

/**
 * Standard login; on local the form starts filled with the demo account.
 */
class Login extends BaseLogin
{
    public function mount(): void
    {
        parent::mount();

        if ($demo = DemoLogin::credentials('institute')) {
            $this->form->fill(['email' => $demo['login'], 'password' => $demo['password'], 'remember' => false]);
        }
    }
}
