<?php

namespace App\Services;

use App\Models\AcademicSession;
use Illuminate\Support\Facades\DB;

class AcademicSessionService
{
    /** Marks one session current and unsets every other. */
    public function makeCurrent(AcademicSession $session): void
    {
        DB::transaction(function () use ($session) {
            AcademicSession::query()->whereKeyNot($session->getKey())->where('is_current', true)->update(['is_current' => false]);
            $session->forceFill(['is_current' => true])->save();
        });
    }
}
