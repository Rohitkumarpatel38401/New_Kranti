@php
    $course = $enrollment->course;
    $institute = $enrollment->institute;
    $revoked = ! $certificate->isValid();
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Certificate {{ $certificate->certificate_no }}</title>
<style>
    @page { margin: 0; }
    body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1e1b4b; }
    .frame { position: absolute; top: 18px; left: 18px; right: 18px; bottom: 18px; border: 6px double #4f46e5; padding: 34px 48px; }
    .brand { text-align: center; font-size: 28px; font-weight: bold; color: #4f46e5; letter-spacing: 1px; }
    .sub { text-align: center; font-size: 12px; color: #6b7280; margin-top: 2px; }
    .title { text-align: center; font-size: 34px; font-weight: bold; margin: 26px 0 8px; color: #312e81; }
    .lead { text-align: center; font-size: 14px; margin-bottom: 14px; }
    .name { text-align: center; font-size: 30px; font-weight: bold; border-bottom: 2px solid #c7d2fe; margin: 0 120px 12px; padding-bottom: 6px; }
    .body { text-align: center; font-size: 14px; line-height: 1.9; }
    .meta { position: absolute; bottom: 40px; left: 48px; font-size: 11px; line-height: 1.7; }
    .qr { position: absolute; bottom: 36px; right: 48px; text-align: center; font-size: 9px; color: #6b7280; }
    .sign { position: absolute; bottom: 44px; left: 0; right: 0; text-align: center; font-size: 12px; }
    .stamp { position: absolute; top: 40%; left: 18%; font-size: 90px; color: rgba(220, 38, 38, .22); transform: rotate(-20deg); font-weight: bold; }
</style>
</head>
<body>
<div class="frame">
    @if ($revoked)
        <div class="stamp">REVOKED</div>
    @endif
    <div class="brand">KRANTI COMPUTER TRAINING INSTITUTE</div>
    <div class="sub">Head Office: Prayagraj · Study centre: {{ $institute->name }} ({{ $institute->code }})</div>

    <div class="title">Certificate of Completion</div>
    <div class="lead">This is to certify that</div>
    <div class="name">{{ $student->full_name }}</div>
    <div class="body">
        son / daughter of <strong>{{ $student->fatherName() ?? '—' }}</strong>,
        enrollment no. <strong>{{ $enrollment->enrollment_no }}</strong>,<br>
        has successfully completed the course
        <strong>{{ $course->code }} — {{ $course->localized('name', 'en') }}</strong>
        ({{ $course->durationLabel() }})<br>
        @if ($result)
            securing <strong>{{ $result->percentage }}%</strong> with grade <strong>{{ $result->grade }}</strong>.
        @endif
    </div>

    <div class="meta">
        Certificate no. <strong>{{ $certificate->certificate_no }}</strong><br>
        Issued on {{ $certificate->issued_on->format('d M Y') }}<br>
        Verify at {{ url('/students/verification') }}
    </div>
    <div class="sign">______________________<br>Director, KCTI</div>
    <div class="qr"><img src="{{ $qr }}" width="92" height="92" alt="QR"><br>Scan to verify</div>
</div>
</body>
</html>
