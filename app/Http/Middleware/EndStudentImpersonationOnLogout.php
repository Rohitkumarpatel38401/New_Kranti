<?php

namespace App\Http\Middleware;

use App\Services\StudentImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament's logout invalidates the whole session, which would also sign out the admin or institute user
 * who is viewing the portal. While impersonating, "Sign out" ends the impersonation instead.
 */
class EndStudentImpersonationOnLogout
{
    public function __construct(private StudentImpersonationService $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('filament.student.auth.logout') && $this->impersonation->isImpersonating()) {
            return redirect()->route('student-impersonation.leave');
        }

        return $next($request);
    }
}
