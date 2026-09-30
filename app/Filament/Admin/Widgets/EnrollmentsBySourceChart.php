<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\Institute;
use Filament\Widgets\ChartWidget;

/**
 * Monthly enrollments for the last 12 months: direct admissions at the main institute vs franchises.
 * Grouped in PHP so it behaves the same on SQLite and MariaDB.
 */
class EnrollmentsBySourceChart extends ChartWidget
{
    // Loads rows into PHP: no background polling.
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    public function getHeading(): ?string
    {
        return 'Enrollments by month';
    }

    public static function canView(): bool
    {
        return (bool) auth('admin')->user()?->can('viewAny', Enrollment::class);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(fn (int $ago) => now()->startOfMonth()->subMonths($ago));
        $keys = $months->map->format('Y-m')->all();
        $main = array_fill_keys($keys, 0);
        $franchise = array_fill_keys($keys, 0);
        $mainIds = Institute::query()->where('is_main', true)->pluck('id')->all();

        Enrollment::query()
            ->whereDate('admission_date', '>=', $months->first()->toDateString())
            ->whereNotIn('status', [EnrollmentStatus::Draft, EnrollmentStatus::Rejected, EnrollmentStatus::Cancelled])
            ->get(['institute_id', 'admission_date'])
            ->each(function (Enrollment $e) use (&$main, &$franchise, $mainIds) {
                $key = $e->admission_date->format('Y-m');

                if (! array_key_exists($key, $main)) {
                    return;
                }

                in_array($e->institute_id, $mainIds, true) ? $main[$key]++ : $franchise[$key]++;
            });

        return [
            'datasets' => [
                ['label' => 'Main institute', 'data' => array_values($main), 'borderColor' => '#4f46e5', 'backgroundColor' => 'rgba(79,70,229,.15)'],
                ['label' => 'Franchises', 'data' => array_values($franchise), 'borderColor' => '#f43f5e', 'backgroundColor' => 'rgba(244,63,94,.15)'],
            ],
            'labels' => $months->map->format('M Y')->all(),
        ];
    }
}
