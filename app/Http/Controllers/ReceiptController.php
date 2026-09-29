<?php

namespace App\Http\Controllers;

use App\Models\FeePayment;
use App\Services\FeeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function show(FeePayment $payment, FeeService $fees): Response
    {
        Gate::forUser(auth()->user())->authorize('view', $payment);

        $payment->load(['institute', 'student.guardians', 'enrollment.course', 'receivedBy', 'receivedByAdmin']);

        return Pdf::loadView('pdf.receipt', ['payment' => $payment, 'summary' => $fees->summary($payment->enrollment)])
            ->setPaper('a5', 'landscape')
            ->stream(str_replace('/', '-', $payment->receipt_no).'.pdf');
    }
}
