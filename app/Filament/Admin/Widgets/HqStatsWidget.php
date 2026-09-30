<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\EnrollmentStatus;
use App\Enums\InstituteStatus;
use App\Enums\WalletTransactionType;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\Student;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * HQ at a glance. Each figure is shown only to admins who may view the underlying model.
 */
class HqStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $admin = auth('admin')->user();
        $monthStart = now()->startOfMonth()->toDateString();
        $today = now()->toDateString();
        $stats = [];

        if ($admin?->can('viewAny', Institute::class)) {
            $byStatus = Institute::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $stats[] = Stat::make('Active institutes', (int) ($byStatus[InstituteStatus::Active->value] ?? 0))
                ->description(sprintf('%d pending · %d suspended',
                    (int) ($byStatus[InstituteStatus::Pending->value] ?? 0),
                    (int) ($byStatus[InstituteStatus::Suspended->value] ?? 0)));
        }

        if ($admin?->can('viewAny', Student::class)) {
            $stats[] = Stat::make('Active students', Enrollment::query()->where('status', EnrollmentStatus::Active)->distinct()->count('student_id'));
        }

        if ($admin?->can('viewAny', Enrollment::class)) {
            // whereDate: date columns may carry a time part on some drivers.
            $stats[] = Stat::make('Enrollments this month', Enrollment::query()
                ->whereDate('admission_date', '>=', $monthStart)->whereDate('admission_date', '<=', $today)
                ->whereNotIn('status', [EnrollmentStatus::Draft, EnrollmentStatus::Rejected, EnrollmentStatus::Cancelled])->count());
        }

        if ($admin?->can('viewAny', WalletTransaction::class)) {
            $paise = WalletTransaction::query()->where('type', WalletTransactionType::Credit)
                ->where('created_at', '>=', now()->startOfMonth())
                ->get(['amount'])->sum(fn ($t) => WalletService::toPaise((string) $t->amount));
            $stats[] = Stat::make('Wallet credits this month', '₹'.number_format((float) WalletService::fromPaise($paise), 2));
        }

        return $stats;
    }
}
