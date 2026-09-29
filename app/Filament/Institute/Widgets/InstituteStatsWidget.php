<?php

namespace App\Filament\Institute\Widgets;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\FeePaymentStatus;
use App\Filament\Shared\DuesTable;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Enrollment;
use App\Models\FeePayment;
use App\Services\WalletService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The institute's day at a glance. Every query is tenant-scoped through the current institute.
 */
class InstituteStatsWidget extends StatsOverviewWidget
{
    public const LOW_BALANCE = 500;

    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        $institute = auth('institute')->user()?->institute;

        if (! $institute) {
            return [];
        }

        $balance = app(WalletService::class)->balance($institute);
        $low = ! $institute->wallet_exempt && (float) $balance < self::LOW_BALANCE;

        $collected = FeePayment::query()->where('status', FeePaymentStatus::Valid)
            // whereDate: date columns may carry a time part, which breaks a plain BETWEEN on the last day.
            ->whereDate('paid_on', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('paid_on', '<=', now()->toDateString())->sum('amount');

        $dues = DuesTable::query()->get()
            ->sum(fn (Enrollment $e) => (float) $e->net_fee - (float) $e->paid_total);

        $sessionIds = AttendanceSession::query()->whereDate('date', now()->toDateString())->pluck('id');
        $marked = AttendanceRecord::query()->whereIn('attendance_session_id', $sessionIds)->count();
        $attended = AttendanceRecord::query()->whereIn('attendance_session_id', $sessionIds)
            ->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Late])->count();

        return [
            Stat::make('Wallet balance', $this->money($balance))
                ->description($low ? 'Low balance — recharge before submitting enrollments' : ($institute->wallet_exempt ? 'Main institute: no HQ fees' : 'Available for HQ fees'))
                ->color($low ? 'danger' : 'success'),
            Stat::make('Active students', Enrollment::query()->where('status', EnrollmentStatus::Active)->distinct('student_id')->count('student_id')),
            Stat::make('Fees collected this month', $this->money($collected)),
            Stat::make('Fees due', $this->money($dues))->color($dues > 0 ? 'warning' : 'success'),
            Stat::make("Today's attendance", $marked ? "{$attended} / {$marked}" : 'Not taken yet'),
        ];
    }

    private function money(float|string $amount): string
    {
        return '₹'.number_format((float) $amount, 2);
    }
}
