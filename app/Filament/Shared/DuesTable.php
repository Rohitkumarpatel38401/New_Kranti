<?php

namespace App\Filament\Shared;

use App\Enums\EnrollmentStatus;
use App\Enums\FeePaymentStatus;
use App\Enums\InstallmentStatus;
use App\Models\Enrollment;
use App\Services\FeeService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Approved enrollments that still owe fees. The query is tenant-scoped through Enrollment's global scope.
 */
class DuesTable
{
    public static function query(): Builder
    {
        $paid = DB::table('fee_payments')->selectRaw('coalesce(sum(amount), 0)')
            ->whereColumn('fee_payments.enrollment_id', 'enrollments.id')
            ->where('status', FeePaymentStatus::Valid->value);

        return Enrollment::query()
            ->select('enrollments.*')
            ->selectSub($paid, 'paid_total')
            ->whereIn('status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->whereRaw('enrollments.net_fee > ('.$paid->toSql().')', $paid->getBindings())
            ->with(['student', 'course', 'institute']);
    }

    public static function configure(Table $table, bool $showInstitute = false): Table
    {
        return $table
            ->query(self::query())
            ->defaultSort('admission_date')
            ->columns(array_values(array_filter([
                $showInstitute ? TextColumn::make('institute.code')->label('Institute')
                    ->description(fn (Enrollment $r) => $r->institute?->name) : null,
                TextColumn::make('student.registration_no')->label('Reg. no.')
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('student', fn (Builder $st) => $st->where('registration_no', 'like', "%{$s}%")
                        ->orWhere('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))),
                TextColumn::make('student.first_name')->label('Student')
                    ->formatStateUsing(fn (Enrollment $r) => $r->student?->full_name),
                TextColumn::make('student.phone')->label('Phone'),
                TextColumn::make('course.code')->label('Course'),
                TextColumn::make('net_fee')->label('Fee')->money('INR'),
                TextColumn::make('paid_total')->label('Paid')->money('INR'),
                TextColumn::make('due')->label('Due')->money('INR')->weight('bold')
                    ->state(fn (Enrollment $r) => app(FeeService::class)->summary($r)['due']),
                TextColumn::make('next_due')->label('Next instalment')
                    ->state(fn (Enrollment $r) => self::next($r)['label']),
                TextColumn::make('standing')->label('Standing')->badge()
                    ->state(fn (Enrollment $r) => self::next($r)['status']?->label())
                    ->color(fn (Enrollment $r) => self::next($r)['status'] === InstallmentStatus::Overdue ? 'danger' : 'warning'),
            ])))
            ->filters([
                TernaryFilter::make('overdue')->label('Overdue only')
                    ->queries(
                        true: fn (Builder $q) => $q->whereExists(fn ($sub) => $sub->from('fee_installments')
                            ->whereColumn('fee_installments.enrollment_id', 'enrollments.id')->whereDate('due_on', '<', now())),
                        false: fn (Builder $q) => $q,
                    ),
            ]);
    }

    /** @return array{label: ?string, status: ?InstallmentStatus} First instalment not fully paid. */
    public static function next(Enrollment $enrollment): array
    {
        $row = app(FeeService::class)->plan($enrollment)->first(fn ($i) => $i['status'] !== InstallmentStatus::Paid);

        return $row
            ? ['label' => $row['due_on']->format('d M Y').' · ₹'.number_format((float) $row['amount'] - (float) $row['paid'], 2), 'status' => $row['status']]
            : ['label' => null, 'status' => null];
    }
}
