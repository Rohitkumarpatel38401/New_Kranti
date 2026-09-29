<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\InstituteUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Daily batch attendance. One session per batch per date; saving again replaces the marks.
 */
class AttendanceService
{
    /** @param  array<int, string|AttendanceStatus>  $marks  enrollment id => status */
    public function save(Batch $batch, Carbon $date, array $marks, InstituteUser $by): AttendanceSession
    {
        if (! $by->can('take_attendance') || $by->institute_id !== $batch->institute_id) {
            throw new DomainRuleException('You are not allowed to take attendance for this batch.');
        }

        if ($date->copy()->startOfDay()->isAfter(now()->startOfDay())) {
            throw new DomainRuleException('Attendance cannot be marked for a future date.');
        }

        if ($marks === []) {
            throw new DomainRuleException('Mark at least one student.');
        }

        $statuses = [];

        foreach ($marks as $enrollmentId => $status) {
            $statuses[(int) $enrollmentId] = $status instanceof AttendanceStatus ? $status
                : (AttendanceStatus::tryFrom((string) $status) ?? throw new DomainRuleException("Unknown attendance status [{$status}]."));
        }

        $valid = Enrollment::query()->withoutGlobalScopes()
            ->where('batch_id', $batch->id)
            ->where('status', EnrollmentStatus::Active)
            ->whereIn('id', array_keys($statuses))
            ->pluck('id')->all();

        if (count($valid) !== count($statuses)) {
            throw new DomainRuleException('Some students are not active in this batch.');
        }

        return DB::transaction(function () use ($batch, $date, $statuses, $by) {
            // whereDate: the date cast may store a time part, so match on the day only.
            $session = AttendanceSession::query()->withoutGlobalScopes()
                ->where('batch_id', $batch->id)->whereDate('date', $date->toDateString())
                ->lockForUpdate()->first()
                ?? AttendanceSession::create([
                    'batch_id' => $batch->id, 'date' => $date->toDateString(),
                    'institute_id' => $batch->institute_id, 'taken_by_user_id' => $by->id,
                ]);
            $session->update(['taken_by_user_id' => $by->id]);

            foreach ($statuses as $enrollmentId => $status) {
                AttendanceRecord::query()->updateOrCreate(
                    ['attendance_session_id' => $session->id, 'enrollment_id' => $enrollmentId],
                    ['status' => $status],
                );
            }

            return $session;
        });
    }

    /** Present + late over all marked days except leave; null when nothing is marked. */
    public function percentage(Enrollment $enrollment): ?float
    {
        $counts = AttendanceRecord::query()->where('enrollment_id', $enrollment->id)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $attended = (int) ($counts[AttendanceStatus::Present->value] ?? 0) + (int) ($counts[AttendanceStatus::Late->value] ?? 0);
        $counted = $attended + (int) ($counts[AttendanceStatus::Absent->value] ?? 0);

        return $counted === 0 ? null : round($attended * 100 / $counted, 2);
    }
}
