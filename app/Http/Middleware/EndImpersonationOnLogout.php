<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament's logout invalidates the whole session, which would also sign the admin out.
 * While impersonating, "Sign out" ends the impersonation instead.
 */
class EndImpersonationOnLogout
{
    public function __construct(private ImpersonationService $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('filament.institute.auth.logout') && $this->impersonation->isImpersonating()) {
            return redirect()->route('impersonation.leave');
        }

        return $next($request);
    }
}
