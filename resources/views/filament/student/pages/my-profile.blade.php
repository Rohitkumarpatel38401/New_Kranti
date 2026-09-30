<x-filament-panels::page>
    <style>
        .kc-profile { display: grid; gap: 24px; grid-template-columns: 140px 1fr; align-items: start; }
        @media (max-width: 640px) { .kc-profile { grid-template-columns: 1fr; } }
        .kc-profile img, .kc-profile .kc-avatar { width: 140px; height: 160px; object-fit: cover; border-radius: 12px; background: rgba(79,70,229,.1); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; color: #4f46e5; }
        .kc-dl { display: grid; grid-template-columns: max-content 1fr; gap: 8px 20px; margin: 0; }
        .kc-dl dt { opacity: .7; font-size: .85rem; } .kc-dl dd { margin: 0; font-weight: 600; }
    </style>
    <x-filament::section>
        <div class="kc-profile">
            @if ($photo)
                <img src="{{ $photo }}" alt="{{ $student->full_name }}">
            @else
                <div class="kc-avatar">{{ mb_substr($student->first_name, 0, 1) }}</div>
            @endif
            <dl class="kc-dl">
                <dt>{{ __('portal.profile.registration_no') }}</dt><dd>{{ $student->registration_no }}</dd>
                <dt>{{ __('portal.profile.name') }}</dt><dd>{{ $student->full_name }}</dd>
                <dt>{{ __('portal.profile.dob') }}</dt><dd>{{ $student->personal?->dob?->format('d M Y') ?? '—' }}</dd>
                <dt>{{ __('portal.profile.gender') }}</dt><dd>{{ $student->personal?->gender ? \Illuminate\Support\Str::title((string) ($student->personal->gender->value ?? $student->personal->gender)) : '—' }}</dd>
                <dt>{{ __('portal.profile.phone') }}</dt><dd>{{ $student->phone }}</dd>
                <dt>{{ __('portal.profile.email') }}</dt><dd>{{ $student->email ?? '—' }}</dd>
                <dt>{{ __('portal.profile.centre') }}</dt><dd>{{ $student->institute?->name }} ({{ $student->institute?->code }})</dd>
                @foreach ($student->guardians as $guardian)
                    <dt>{{ $guardian->relation->label() }}</dt><dd>{{ $guardian->name }}</dd>
                @endforeach
                @if ($address)
                    <dt>{{ __('portal.profile.address') }}</dt>
                    <dd>{{ collect([$address->line_1, $address->line_2, $address->city, $address->district, $address->state, $address->pincode])->filter()->implode(', ') }}</dd>
                @endif
            </dl>
        </div>
        <p style="margin-top:16px;opacity:.7;font-size:.85rem">{{ __('portal.profile.correction') }}</p>
    </x-filament::section>
</x-filament-panels::page>
