@php($impersonator = app(\App\Services\ImpersonationService::class)->impersonator())

@if ($impersonator)
    <div style="display:flex;align-items:center;justify-content:center;gap:12px;padding:10px 16px;background:#eef2ff;border-bottom:1px solid #c7d2fe;color:#3730a3;font-size:14px;font-weight:600;">
        <span>{{ __('Viewing as :institute (admin: :admin)', ['institute' => filament()->auth()->user()?->institute?->name, 'admin' => $impersonator->name]) }}</span>
        <a href="{{ route('impersonation.leave') }}" style="display:inline-flex;align-items:center;height:32px;padding:0 12px;border-radius:8px;background:#4f46e5;color:#fff;text-decoration:none;font-size:13px;font-weight:700;">
            {{ __('Return to Admin') }}
        </a>
    </div>
@endif
