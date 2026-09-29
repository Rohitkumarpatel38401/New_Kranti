<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify a certificate | Kranti Computer Training Institute</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f3ff; color: #1e1b4b; margin: 0; padding: 32px 16px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 8px 30px rgba(79,70,229,.12); }
        label { display: block; font-size: 13px; margin: 12px 0 4px; } input { width: 100%; padding: 10px; border: 1px solid #c7d2fe; border-radius: 8px; box-sizing: border-box; }
        button { margin-top: 14px; background: #4f46e5; color: #fff; border: 0; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .or { text-align: center; color: #6b7280; margin: 16px 0 0; } .err { color: #b91c1c; font-size: 13px; }
    </style>
</head>
<body>
<div class="card">
    <h1 style="margin-top:0;font-size:22px">Verify a KCTI certificate</h1>
    @if ($errors->any())
        <p class="err">{{ $errors->first() }}</p>
    @endif
    <form method="POST" action="{{ route('site.students.verification.lookup') }}">
        @csrf
        <label for="certificate_no">Certificate number</label>
        <input id="certificate_no" name="certificate_no" value="{{ old('certificate_no') }}" placeholder="KCTI/C/2026/000123">
        <p class="or">— or —</p>
        <label for="enrollment_no">Enrollment number</label>
        <input id="enrollment_no" name="enrollment_no" value="{{ old('enrollment_no') }}" placeholder="KCTI/2026/000123">
        <label for="dob">Date of birth</label>
        <input id="dob" name="dob" type="date" value="{{ old('dob') }}">
        <button type="submit">Verify</button>
    </form>
</div>
</body>
</html>
