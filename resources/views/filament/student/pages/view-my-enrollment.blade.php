@php($money = fn ($v) => \App\Filament\Student\Resources\MyEnrollments\Pages\ViewMyEnrollment::money((string) $v))
<x-filament-panels::page>
    <style>
        .kc-portal-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
        .kc-portal-stat { font-size: .8rem; opacity: .7; } .kc-portal-num { font-size: 1.35rem; font-weight: 700; }
        .kc-portal-table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        .kc-portal-table th, .kc-portal-table td { text-align: left; padding: 8px 10px; border-bottom: 1px solid rgba(127,127,127,.2); }
        .kc-portal-links { display: flex; flex-wrap: wrap; gap: 10px; }
    </style>

    <x-filament::section :heading="__('portal.enrollments.details')">
        <div class="kc-portal-grid">
            <div><div class="kc-portal-stat">{{ __('portal.enrollments.no') }}</div><div class="kc-portal-num" style="font-size:1rem">{{ $enrollment->enrollment_no }}</div></div>
            <div><div class="kc-portal-stat">{{ __('portal.enrollments.course') }}</div><div class="kc-portal-num" style="font-size:1rem">{{ $enrollment->course?->code }} — {{ $enrollment->course?->localized('name') }}</div></div>
            <div><div class="kc-portal-stat">{{ __('portal.enrollments.centre') }}</div><div class="kc-portal-num" style="font-size:1rem">{{ $enrollment->institute?->name }}</div></div>
            <div><div class="kc-portal-stat">{{ __('portal.enrollments.status') }}</div><div class="kc-portal-num" style="font-size:1rem">{{ $enrollment->status->label() }}</div></div>
            <div><div class="kc-portal-stat">{{ __('portal.enrollments.attendance') }}</div><div class="kc-portal-num">{{ $attendance === null ? '—' : number_format($attendance, 2).'%' }}</div></div>
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('portal.fees.title')">
        <div class="kc-portal-grid" style="margin-bottom:16px">
            <div><div class="kc-portal-stat">{{ __('portal.fees.net') }}</div><div class="kc-portal-num">{{ $money($summary['net']) }}</div></div>
            <div><div class="kc-portal-stat">{{ __('portal.fees.paid') }}</div><div class="kc-portal-num">{{ $money($summary['paid']) }}</div></div>
            <div><div class="kc-portal-stat">{{ __('portal.fees.due') }}</div><div class="kc-portal-num" style="{{ (float) $summary['due'] > 0 ? 'color:#d97706' : '' }}">{{ $money($summary['due']) }}</div></div>
        </div>
        <table class="kc-portal-table">
            <thead><tr><th>#</th><th>{{ __('portal.fees.due_on') }}</th><th>{{ __('portal.fees.amount') }}</th><th>{{ __('portal.fees.paid') }}</th><th>{{ __('portal.enrollments.status') }}</th></tr></thead>
            <tbody>
                @foreach ($plan as $row)
                    <tr><td>{{ $row['sequence'] }}</td><td>{{ $row['due_on']->format('d M Y') }}</td><td>{{ $money($row['amount']) }}</td><td>{{ $money($row['paid']) }}</td><td>{{ $row['status']->label() }}</td></tr>
                @endforeach
            </tbody>
        </table>
        @if ($payments->isNotEmpty())
            <h3 style="margin:18px 0 8px;font-weight:600">{{ __('portal.fees.receipts') }}</h3>
            <table class="kc-portal-table">
                <thead><tr><th>{{ __('portal.fees.receipt_no') }}</th><th>{{ __('portal.fees.date') }}</th><th>{{ __('portal.fees.amount') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($payments as $payment)
                        <tr style="{{ $payment->status->value === 'cancelled' ? 'text-decoration:line-through;opacity:.6' : '' }}">
                            <td>{{ $payment->receipt_no }}</td><td>{{ $payment->paid_on->format('d M Y') }}</td><td>{{ $money($payment->amount) }}</td>
                            <td><x-filament::link :href="route('receipts.show', $payment)" target="_blank">{{ __('portal.fees.view_receipt') }}</x-filament::link></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('portal.result.title')">
        @if (! $result)
            <p style="opacity:.7">{{ __('portal.result.not_yet') }}</p>
        @else
            <div class="kc-portal-grid" style="margin-bottom:16px">
                <div><div class="kc-portal-stat">{{ __('portal.result.total') }}</div><div class="kc-portal-num">{{ $result->total_obtained }} / {{ $result->total_max }}</div></div>
                <div><div class="kc-portal-stat">{{ __('portal.result.percentage') }}</div><div class="kc-portal-num">{{ $result->percentage }}%</div></div>
                <div><div class="kc-portal-stat">{{ __('portal.result.grade') }}</div><div class="kc-portal-num">{{ $result->grade }}</div></div>
                <div><div class="kc-portal-stat">{{ __('portal.result.outcome') }}</div><div class="kc-portal-num">{{ $result->result?->label() }}</div></div>
            </div>
            <table class="kc-portal-table">
                <thead><tr><th>{{ __('portal.result.subject') }}</th><th>{{ __('portal.result.theory') }}</th><th>{{ __('portal.result.practical') }}</th><th>{{ __('portal.result.total') }}</th></tr></thead>
                <tbody>
                    @foreach ($result->marks as $mark)
                        <tr>
                            <td>{{ $mark->courseSubject?->subject?->localized('name') }}</td>
                            @if ($mark->is_absent)
                                <td colspan="3">{{ __('portal.result.absent') }}</td>
                            @else
                                <td>{{ $mark->theory ?? '—' }}</td><td>{{ $mark->practical ?? '—' }}</td><td>{{ $mark->total }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section :heading="__('portal.documents.title')">
        <div class="kc-portal-links">
            @if ($canIdCard)
                <x-filament::button tag="a" :href="route('documents.id-card', $enrollment)" target="_blank" color="gray" icon="heroicon-o-identification">{{ __('portal.documents.id_card') }}</x-filament::button>
            @endif
            @if ($canAdmitCard)
                <x-filament::button tag="a" :href="route('documents.admit-card', $enrollment)" target="_blank" color="gray" icon="heroicon-o-ticket">{{ __('portal.documents.admit_card') }}</x-filament::button>
            @endif
            @if ($result)
                <x-filament::button tag="a" :href="route('documents.marksheet', $result)" target="_blank" color="gray" icon="heroicon-o-document-text">{{ __('portal.documents.marksheet') }}</x-filament::button>
            @endif
            @if ($certificate)
                <x-filament::button tag="a" :href="route('documents.certificate', $certificate)" target="_blank" icon="heroicon-o-trophy">{{ __('portal.documents.certificate') }}</x-filament::button>
            @endif
        </div>
    </x-filament::section>
</x-filament-panels::page>
