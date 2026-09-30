<?php

namespace App\Http\Middleware;

use App\Filament\Student\Pages\ChangePassword;
use App\Models\Student;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until a student replaces the initial password, every student panel page leads to Change password.
 * Livewire updates ignore redirects from persistent middleware, so those are refused outright
 * (except for the Change password component itself).
 */
class EnsureStudentPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $student = auth('student')->user();

        if (! $student instanceof Student || ! $student->must_change_password || $this->isChangePassword($request)) {
            return $next($request);
        }

        abort_if($request->hasHeader('X-Livewire'), 403, __('portal.password.required'));

        return redirect()->to(ChangePassword::getUrl(panel: 'student'));
    }

    private function isChangePassword(Request $request): bool
    {
        if ($request->hasHeader('X-Livewire')) {
            $names = collect($request->input('components', []))
                ->map(fn ($c) => json_decode($c['snapshot'] ?? '{}', true)['memo']['name'] ?? null);

            return $names->isNotEmpty() && $names->every(fn ($name) => $name === ChangePassword::class || $name === app(ChangePassword::class)->getName());
        }

        return $request->routeIs(ChangePassword::getRouteName(Filament::getPanel('student')))
            || $request->routeIs('filament.student.auth.logout');
    }
}
