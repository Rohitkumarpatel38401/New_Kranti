@php($impersonator = app(\App\Services\StudentImpersonationService::class)->impersonator())

@if ($impersonator)
    <div style="display:flex;align-items:center;justify-content:center;gap:12px;padding:10px 16px;background:#eef2ff;border-bottom:1px solid #c7d2fe;color:#3730a3;font-size:14px;font-weight:600;">
        <span>{{ __('Viewing the portal as :student (by :by)', ['student' => auth('student')->user()?->full_name, 'by' => $impersonator->name]) }}</span>
        <a href="{{ route('student-impersonation.leave') }}" style="display:inline-flex;align-items:center;height:32px;padding:0 12px;border-radius:8px;background:#4f46e5;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">
            {{ __('Return to my panel') }}
        </a>
    </div>
@endif
