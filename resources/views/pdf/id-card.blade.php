@php
    $student = $enrollment->student;
    $institute = $enrollment->institute;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e1b4b; }
    .card { position: absolute; top: 8px; left: 8px; right: 8px; bottom: 8px; border: 2px solid #4f46e5; border-radius: 8px; }
    .head { background: #4f46e5; color: #fff; text-align: center; padding: 5px 4px; border-radius: 6px 6px 0 0; }
    .head b { font-size: 11px; }
    .photo { text-align: center; margin: 8px 0 4px; }
    .photo img, .ph { width: 70px; height: 84px; border: 1px solid #c7d2fe; }
    .ph { display: inline-block; line-height: 84px; color: #9ca3af; }
    .name { text-align: center; font-size: 12px; font-weight: bold; margin-bottom: 6px; }
    table { width: 100%; padding: 0 8px; } td { padding: 1px 0; } .k { color: #6b7280; width: 40%; }
    .foot { position: absolute; bottom: 6px; left: 0; right: 0; text-align: center; font-size: 8px; color: #6b7280; }
</style>
</head>
<body>
<div class="card">
    <div class="head"><b>KRANTI COMPUTER TRAINING INSTITUTE</b><br>{{ $institute->name }} ({{ $institute->code }})</div>
    <div class="photo">
        @if ($photo)
            <img src="{{ $photo }}" alt="Photo">
        @else
            <span class="ph">PHOTO</span>
        @endif
    </div>
    <div class="name">{{ $student->full_name }}</div>
    <table>
        <tr><td class="k">Reg. no.</td><td>{{ $student->registration_no }}</td></tr>
        <tr><td class="k">Enrollment</td><td>{{ $enrollment->enrollment_no }}</td></tr>
        <tr><td class="k">Course</td><td>{{ $enrollment->course->code }}</td></tr>
        <tr><td class="k">Session</td><td>{{ $enrollment->academicSession?->name }}</td></tr>
        <tr><td class="k">Father</td><td>{{ $student->fatherName() ?? '—' }}</td></tr>
        <tr><td class="k">Phone</td><td>{{ $institute->phone ?? '—' }}</td></tr>
    </table>
    <div class="foot">Student identity card · valid for the course duration</div>
</div>
</body>
</html>
