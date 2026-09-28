<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\CurrentInstitute;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts every request with no institute context, so a long-lived process
 * never carries one request's institute into the next.
 */
class ResetCurrentInstitute
{
    public function __construct(private CurrentInstitute $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->current->forget();

        return $next($request);
    }
}
