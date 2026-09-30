<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudentImpersonationController extends Controller
{
    /** Signed link from the admin or institute panel; "as" names the guard of the person starting it. */
    public function start(Request $request, Student $student, StudentImpersonationService $impersonation): RedirectResponse
    {
        $guard = $request->query('as');
        abort_unless(in_array($guard, ['admin', 'institute'], true), 403);

        $by = auth($guard)->user();
        abort_if($by === null, 403);

        $impersonation->start($by, $student);

        return redirect('/student');
    }

    public function leave(StudentImpersonationService $impersonation): RedirectResponse
    {
        return redirect($impersonation->stop());
    }
}
