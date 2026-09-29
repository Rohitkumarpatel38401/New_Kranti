@php
    $institute = $payment->institute;
    $student = $payment->student;
    $enrollment = $payment->enrollment;
    $cancelled = ! $payment->isValid();
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt {{ $payment->receipt_no }}</title>
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e1b4b; }
    .head { border-bottom: 3px solid #4f46e5; padding-bottom: 10px; margin-bottom: 14px; }
    .brand { font-size: 20px; font-weight: bold; color: #4f46e5; }
    .muted { color: #6b7280; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 6px 4px; vertical-align: top; }
    .grid td { border: 1px solid #e0e7ff; }
    .label { color: #6b7280; width: 32%; }
    .amount { font-size: 18px; font-weight: bold; }
    .stamp { position: fixed; top: 38%; left: 12%; font-size: 72px; color: rgba(220, 38, 38, .25); transform: rotate(-24deg); font-weight: bold; }
    .foot { margin-top: 30px; }
</style>
</head>
<body>
@if ($cancelled)
    <div class="stamp">CANCELLED</div>
@endif
<div class="head">
    <table><tr>
        <td>
            <div class="brand">Kranti Computer Training Institute</div>
            <div>{{ $institute->name }} ({{ $institute->code }})</div>
            <div class="muted">{{ $institute->phone }} {{ $institute->email ? '· '.$institute->email : '' }}</div>
        </td>
        <td style="text-align:right">
            <div style="font-size:16px;font-weight:bold">FEE RECEIPT</div>
            <div>No. <strong>{{ $payment->receipt_no }}</strong></div>
            <div>Date: {{ $payment->paid_on->format('d M Y') }}</div>
        </td>
    </tr></table>
</div>

<table class="grid">
    <tr><td class="label">Student</td><td>{{ $student->full_name }}</td></tr>
    <tr><td class="label">Registration no.</td><td>{{ $student->registration_no }}</td></tr>
    <tr><td class="label">Father's name</td><td>{{ $student->fatherName() ?? '—' }}</td></tr>
    <tr><td class="label">Course</td><td>{{ $enrollment->course->code }} — {{ $enrollment->course->localized('name', 'en') }}</td></tr>
    <tr><td class="label">Enrollment no.</td><td>{{ $enrollment->enrollment_no ?? '—' }}</td></tr>
    <tr><td class="label">Payment mode</td><td>{{ $payment->mode->label() }}{{ $payment->reference_no ? ' · Ref '.$payment->reference_no : '' }}</td></tr>
    <tr><td class="label">Amount received</td><td class="amount">Rs. {{ number_format((float) $payment->amount, 2) }}</td></tr>
    <tr><td class="label">Course fee / paid / due</td><td>Rs. {{ $summary['net'] }} / Rs. {{ $summary['paid'] }} / Rs. {{ $summary['due'] }}</td></tr>
    @if ($cancelled)
        <tr><td class="label">Cancelled</td><td>{{ $payment->cancelled_at?->format('d M Y H:i') }} — {{ $payment->cancel_reason }}</td></tr>
    @endif
</table>

<table class="foot"><tr>
    <td class="muted">Received by {{ $payment->receivedBy?->name ?? $payment->receivedByAdmin?->name ?? '—' }}.<br>Computer-generated receipt.</td>
    <td style="text-align:right">______________________<br>Authorised signatory</td>
</tr></table>
</body>
</html>
