@php
    $enrollment = $result->enrollment;
    $student = $enrollment->student;
    $course = $enrollment->course;
    $institute = $enrollment->institute;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Marksheet {{ $enrollment->enrollment_no }}</title>
<style>
    @page { margin: 30px 34px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e1b4b; }
    .brand { text-align: center; font-size: 20px; font-weight: bold; color: #4f46e5; }
    .sub { text-align: center; color: #6b7280; font-size: 11px; }
    h2 { text-align: center; font-size: 16px; margin: 14px 0; }
    table { width: 100%; border-collapse: collapse; }
    .info td { padding: 4px; }
    .marks th, .marks td { border: 1px solid #c7d2fe; padding: 6px; text-align: center; }
    .marks th { background: #eef2ff; }
    .left { text-align: left !important; }
    .summary td { padding: 6px; font-weight: bold; }
</style>
</head>
<body>
<div class="brand">KRANTI COMPUTER TRAINING INSTITUTE</div>
<div class="sub">Study centre: {{ $institute->name }} ({{ $institute->code }})</div>
<h2>STATEMENT OF MARKS</h2>

<table class="info">
    <tr><td>Name: <strong>{{ $student->full_name }}</strong></td><td>Enrollment no.: <strong>{{ $enrollment->enrollment_no }}</strong></td></tr>
    <tr><td>Father's name: {{ $student->fatherName() ?? '—' }}</td><td>Registration no.: {{ $student->registration_no }}</td></tr>
    <tr><td>Course: {{ $course->code }} — {{ $course->localized('name', 'en') }}</td><td>Exam date: {{ $result->exam_date->format('d M Y') }}</td></tr>
</table>

<table class="marks" style="margin-top:14px">
    <thead>
    <tr><th class="left">Subject</th><th>Theory</th><th>Practical</th><th>Max</th><th>Pass</th><th>Obtained</th></tr>
    </thead>
    <tbody>
    @foreach ($result->marks as $mark)
        <tr>
            <td class="left">{{ $mark->courseSubject->subject?->code }} — {{ $mark->courseSubject->subject?->localized('name', 'en') }}</td>
            <td>{{ $mark->is_absent ? 'AB' : $mark->theory }}</td>
            <td>{{ $mark->is_absent ? 'AB' : $mark->practical }}</td>
            <td>{{ $mark->courseSubject->maxMarks() }}</td>
            <td>{{ $mark->courseSubject->pass_marks }}</td>
            <td>{{ $mark->is_absent ? 'Absent' : $mark->total }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="summary" style="margin-top:12px">
    <tr>
        <td>Total: {{ $result->total_obtained }} / {{ $result->total_max }}</td>
        <td>Percentage: {{ $result->percentage }}%</td>
        <td>Grade: {{ $result->grade ?? '—' }}</td>
        <td>Result: {{ $result->result?->label() ?? '—' }}</td>
    </tr>
</table>

<p style="margin-top:40px;text-align:right">______________________<br>Controller of Examinations, KCTI</p>
</body>
</html>
