<?php

namespace App\Http\Controllers;

use App\Models\Institute;
use App\Services\ImpersonationService;
use Illuminate\Http\RedirectResponse;

class ImpersonationController extends Controller
{
    public function start(Institute $institute, ImpersonationService $impersonation): RedirectResponse
    {
        $impersonation->start(auth('admin')->user(), $institute);

        return redirect('/institute');
    }

    public function leave(ImpersonationService $impersonation): RedirectResponse
    {
        $impersonation->stop();

        return redirect('/admin/institutes');
    }
}
