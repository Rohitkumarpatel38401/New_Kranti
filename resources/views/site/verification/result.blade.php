<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate verification | Kranti Computer Training Institute</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f3ff; color: #1e1b4b; margin: 0; padding: 32px 16px; }
        .card { max-width: 640px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 8px 30px rgba(79,70,229,.12); }
        .ok { color: #15803d; } .bad { color: #b91c1c; }
        dt { font-size: 12px; color: #6b7280; margin-top: 10px; } dd { margin: 2px 0 0; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <h1 style="margin-top:0;font-size:22px">Certificate verification</h1>
    @if (! $data)
        <p class="bad"><strong>No certificate found.</strong> Check the number or scan the QR code again.</p>
    @else
        <p class="{{ $data['valid'] === 'yes' ? 'ok' : 'bad' }}"><strong>{{ $data['valid'] === 'yes' ? 'Genuine certificate' : 'This certificate has been revoked' }}</strong></p>
        <dl>
            <dt>Certificate no.</dt><dd>{{ $data['certificate_no'] }}</dd>
            <dt>Student</dt><dd>{{ $data['student'] }}</dd>
            <dt>Father's name</dt><dd>{{ $data['father'] ?? '—' }}</dd>
            <dt>Course</dt><dd>{{ $data['course'] }}</dd>
            <dt>Grade</dt><dd>{{ $data['grade'] ?? '—' }}</dd>
            <dt>Study centre</dt><dd>{{ $data['institute'] }}</dd>
            <dt>Issued on</dt><dd>{{ $data['issued_on'] }}</dd>
        </dl>
    @endif
</div>
</body>
</html>
