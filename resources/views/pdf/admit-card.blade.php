@php
    $student = $enrollment->student;
    $institute = $enrollment->institute;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 30px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e1b4b; }
    .brand { text-align: center; font-size: 20px; font-weight: bold; color: #4f46e5; }
    h2 { text-align: center; font-size: 16px; margin: 10px 0 16px; }
    table { width: 100%; border-collapse: collapse; }
    .info td { border: 1px solid #c7d2fe; padding: 7px; } .k { color: #6b7280; width: 28%; }
    .photo { width: 110px; height: 132px; border: 1px solid #c7d2fe; text-align: center; vertical-align: middle; color: #9ca3af; }
    ul { margin-top: 18px; line-height: 1.7; }
</style>
</head>
<body>
<div class="brand">KRANTI COMPUTER TRAINING INSTITUTE</div>
<h2>ADMIT CARD</h2>
<table class="info">
    <tr>
        <td class="k">Name</td><td><strong>{{ $student->full_name }}</strong></td>
        <td rowspan="5" class="photo">@if ($photo)<img src="{{ $photo }}" width="108" height="130" alt="Photo">@else PHOTO @endif</td>
    </tr>
    <tr><td class="k">Father's name</td><td>{{ $student->fatherName() ?? '—' }}</td></tr>
    <tr><td class="k">Enrollment no.</td><td>{{ $enrollment->enrollment_no }}</td></tr>
    <tr><td class="k">Course</td><td>{{ $enrollment->course->code }} — {{ $enrollment->course->localized('name', 'en') }}</td></tr>
    <tr><td class="k">Exam centre</td><td>{{ $institute->name }} ({{ $institute->code }})</td></tr>
</table>
<ul>
    <li>Bring this admit card and a photo ID to the examination.</li>
    <li>Reach the centre 30 minutes before the exam.</li>
    <li>Mobile phones are not allowed in the examination room.</li>
</ul>
<p style="margin-top:50px;text-align:right">______________________<br>Centre in-charge</p>
</body>
</html>
