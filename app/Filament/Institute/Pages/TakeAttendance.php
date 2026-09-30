<?php

namespace App\Filament\Institute\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Services\AttendanceService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Pick a batch and a day, mark everyone in one grid. Re-opening the same day edits it.
 */
class TakeAttendance extends Page
{
    protected string $view = 'filament.institute.pages.take-attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Take attendance';

    public ?int $batchId = null;

    public string $date = '';

    /** @var array<int, string> enrollment id => status */
    public array $marks = [];

    /** @var array<int, array{id: int, name: string, reg: string}> */
    public array $roster = [];

    public static function canAccess(): bool
    {
        return (bool) auth('institute')->user()?->can('take_attendance');
    }

    public function mount(): void
    {
        $this->date = now()->toDateString();
    }

    /** @return array<int, string> Own institute's running or upcoming batches (tenant-scoped). */
    public function getBatchOptions(): array
    {
        return Batch::query()->whereIn('status', [BatchStatus::Running, BatchStatus::Upcoming])
            ->with('course')->orderBy('name')->get()
            ->mapWithKeys(fn (Batch $b) => [$b->id => "{$b->name} ({$b->course?->code})"])->all();
    }

    public function getStatuses(): array
    {
        return collect(AttendanceStatus::cases())->mapWithKeys(fn (AttendanceStatus $s) => [$s->value => $s->label()])->all();
    }

    public function loadRoster(): void
    {
        $batch = $this->batch();

        if (! $batch) {
            $this->roster = [];
            $this->marks = [];

            return;
        }

        $enrollments = Enrollment::query()->where('batch_id', $batch->id)->where('status', EnrollmentStatus::Active)
            ->with('student')->get()->sortBy(fn (Enrollment $e) => $e->student?->full_name);

        $this->roster = $enrollments->map(fn (Enrollment $e) => [
            'id' => $e->id, 'name' => $e->student?->full_name, 'reg' => $e->student?->registration_no,
        ])->values()->all();

        $session = AttendanceSession::query()->where('batch_id', $batch->id)->whereDate('date', $this->date)->first();
        $saved = $session ? AttendanceRecord::query()->where('attendance_session_id', $session->id)->pluck('status', 'enrollment_id') : collect();

        $this->marks = $enrollments->mapWithKeys(fn (Enrollment $e) => [
            $e->id => ($saved[$e->id] ?? null) instanceof AttendanceStatus ? $saved[$e->id]->value : ($saved[$e->id] ?? AttendanceStatus::Present->value),
        ])->all();
    }

    public function updatedBatchId(): void
    {
        $this->loadRoster();
    }

    public function updatedDate(): void
    {
        $this->loadRoster();
    }

    public function markAll(string $status): void
    {
        if (AttendanceStatus::tryFrom($status)) {
            $this->marks = array_fill_keys(array_keys($this->marks), $status);
        }
    }

    public function save(): void
    {
        $batch = $this->batch();

        try {
            if (! $batch) {
                throw new DomainRuleException('Choose a batch.');
            }

            app(AttendanceService::class)->save($batch, Carbon::parse($this->date), $this->marks, auth('institute')->user());
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Attendance saved for '.Carbon::parse($this->date)->format('d M Y'))->send();
    }

    private function batch(): ?Batch
    {
        // Tenant-scoped: a tampered id for another institute's batch is simply not found.
        return $this->batchId ? Batch::query()->find($this->batchId) : null;
    }
}
