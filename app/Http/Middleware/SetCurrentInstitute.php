<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentInstitute;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentInstitute
{
    public function __construct(private CurrentInstitute $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Not cleared afterwards: Livewire applies persistent middleware first and updates the component
        // after this returns. ResetCurrentInstitute clears it at the start of every request instead.
        $this->current->set(Filament::auth()->user()?->institute_id);

        return $next($request);
    }
}
