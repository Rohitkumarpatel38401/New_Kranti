<?php

namespace App\Filament\Student\Widgets;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Services\AttendanceService;
use App\Services\FeeService;
use App\Services\WalletService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Student dashboard: courses in progress, fees still due, and attendance in the latest active course.
 */
class StudentOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $enrollments = Enrollment::query()
            ->where('student_id', auth('student')->id())
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->latest('admission_date')->get();

        $fees = app(FeeService::class);
        $duePaise = $enrollments->sum(fn (Enrollment $e) => WalletService::toPaise($fees->summary($e)['due']));
        $latest = $enrollments->firstWhere('status', EnrollmentStatus::Active);
        $attendance = $latest ? app(AttendanceService::class)->percentage($latest) : null;

        return [
            Stat::make(__('portal.dashboard.active'), $enrollments->where('status', EnrollmentStatus::Active)->count()),
            Stat::make(__('portal.fees.due'), '₹'.number_format((float) WalletService::fromPaise($duePaise), 2))
                ->color($duePaise > 0 ? 'warning' : 'success'),
            Stat::make(__('portal.enrollments.attendance'), $attendance === null ? '—' : number_format($attendance, 2).'%'),
        ];
    }
}
