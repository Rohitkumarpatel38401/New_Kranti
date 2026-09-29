<?php

namespace App\Filament\Shared;

use App\Enums\EnrollmentStatus;
use App\Enums\FeePaymentStatus;
use App\Enums\InstallmentStatus;
use App\Models\Enrollment;
use App\Services\FeeService;
use App\Services\WalletService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Approved enrollments that still owe fees. The query is tenant-scoped through Enrollment's global scope.
 */
class DuesTable
{
    /** @var array<int, array{label: ?string, status: ?InstallmentStatus}> per-request memo for next() */
    private static array $next = [];

    private static function paidSubquery(): QueryBuilder
    {
        return DB::table('fee_payments')->selectRaw('coalesce(sum(amount), 0)')
            ->whereColumn('fee_payments.enrollment_id', 'enrollments.id')
            ->where('status', FeePaymentStatus::Valid->value);
    }

    /** Owing enrollments, without select or eager loads (for aggregates). */
    public static function base(): Builder
    {
        $paid = self::paidSubquery();

        return Enrollment::query()
            ->whereIn('enrollments.status', [EnrollmentStatus::Active, EnrollmentStatus::Completed])
            ->whereRaw('enrollments.net_fee > ('.$paid->toSql().')', $paid->getBindings());
    }

    public static function query(): Builder
    {
        return self::base()
            ->select('enrollments.*')
            ->selectSub(self::paidSubquery(), 'paid_total')
            ->with(['student', 'course', 'institute']);
    }

    /** Total still due across owing enrollments, as an exact decimal string. */
    public static function totalDue(): string
    {
        $paid = self::paidSubquery();
        $row = self::base()->selectRaw('coalesce(sum(enrollments.net_fee), 0) as net, coalesce(sum(('.$paid->toSql().')), 0) as paid', $paid->getBindings())->toBase()->first();

        return WalletService::fromPaise(max(WalletService::toPaise(self::decimal($row->net)) - WalletService::toPaise(self::decimal($row->paid)), 0));
    }

    /**
     * Overdue = an instalment due before today that is not fully covered by valid payments,
     * or (no plan) the admission date has passed — the same rule as the Overdue badge.
     */
    public static function overdue(Builder $query): Builder
    {
        $paid = self::paidSubquery();
        $today = now()->toDateString();

        return $query->where(function (Builder $q) use ($paid, $today) {
            $q->whereExists(fn (QueryBuilder $sub) => $sub->from('fee_installments as i')
                ->whereColumn('i.enrollment_id', 'enrollments.id')
                ->whereDate('i.due_on', '<', $today)
                ->whereRaw('(select sum(j.amount) from fee_installments j where j.enrollment_id = i.enrollment_id and j.sequence <= i.sequence) > ('.$paid->toSql().')', $paid->getBindings()))
                ->orWhere(fn (Builder $noPlan) => $noPlan
                    ->whereNotExists(fn (QueryBuilder $sub) => $sub->from('fee_installments')->whereColumn('fee_installments.enrollment_id', 'enrollments.id'))
                    ->whereDate('enrollments.admission_date', '<', $today));
        });
    }

    public static function configure(Table $table, bool $showInstitute = false): Table
    {
        self::$next = [];

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
                    ->state(fn (Enrollment $r) => WalletService::fromPaise(WalletService::toPaise((string) $r->net_fee) - WalletService::toPaise(self::decimal($r->paid_total)))),
                TextColumn::make('next_due')->label('Next instalment')
                    ->state(fn (Enrollment $r) => self::next($r)['label']),
                TextColumn::make('standing')->label('Standing')->badge()
                    ->state(fn (Enrollment $r) => self::next($r)['status']?->label())
                    ->color(fn (Enrollment $r) => self::next($r)['status'] === InstallmentStatus::Overdue ? 'danger' : 'warning'),
            ])))
            ->filters([
                TernaryFilter::make('overdue')->label('Overdue only')
                    ->queries(true: fn (Builder $q) => self::overdue($q), false: fn (Builder $q) => $q),
            ]);
    }

    /** @return array{label: ?string, status: ?InstallmentStatus} First instalment not fully paid (memoised per row). */
    public static function next(Enrollment $enrollment): array
    {
        return self::$next[$enrollment->id] ??= (function () use ($enrollment) {
            $row = app(FeeService::class)->plan($enrollment)->first(fn ($i) => $i['status'] !== InstallmentStatus::Paid);

            if (! $row) {
                return ['label' => null, 'status' => null];
            }

            $remaining = WalletService::fromPaise(WalletService::toPaise($row['amount']) - WalletService::toPaise($row['paid']));

            return ['label' => $row['due_on']->format('d M Y').' · ₹'.number_format((float) $remaining, 2), 'status' => $row['status']];
        })();
    }

    /** Normalises DB decimals (which may come back as floats or long strings) to a two-decimal string. */
    private static function decimal(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }
}
