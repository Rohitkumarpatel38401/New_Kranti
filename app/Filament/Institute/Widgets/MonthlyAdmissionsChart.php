<?php

namespace App\Filament\Institute\Widgets;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use Filament\Widgets\ChartWidget;

/**
 * This institute's admissions per month for the last 6 months (tenant-scoped through the current institute).
 */
class MonthlyAdmissionsChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public function getHeading(): ?string
    {
        return 'Admissions by month';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = collect(range(5, 0))->map(fn (int $ago) => now()->startOfMonth()->subMonths($ago));
        $counts = array_fill_keys($months->map->format('Y-m')->all(), 0);

        Enrollment::query()
            ->whereDate('admission_date', '>=', $months->first()->toDateString())
            ->whereNotIn('status', [EnrollmentStatus::Draft, EnrollmentStatus::Rejected, EnrollmentStatus::Cancelled])
            ->get(['admission_date'])
            ->each(function (Enrollment $e) use (&$counts) {
                $key = $e->admission_date->format('Y-m');

                if (array_key_exists($key, $counts)) {
                    $counts[$key]++;
                }
            });

        return [
            'datasets' => [['label' => 'Admissions', 'data' => array_values($counts), 'backgroundColor' => '#4f46e5']],
            'labels' => $months->map->format('M Y')->all(),
        ];
    }
}
