<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeePaymentStatus;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMode;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Enrollment;
use App\Models\FeePayment;
use App\Models\InstituteUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Student fees an institute collects for itself (separate from the HQ wallet).
 * Due = net fee − valid payments; installment status is derived by applying payments in order.
 */
class FeeService
{
    public function __construct(private NumberSequenceService $sequences) {}

    /**
     * @param  array<int, array{due_on: string, amount: string|int|float}>  $rows
     */
    public function setPlan(Enrollment $enrollment, array $rows, Model $by): void
    {
        if ($rows === []) {
            throw new DomainRuleException('Add at least one installment.');
        }

        $total = 0;
        $previous = null;
        $clean = [];

        foreach (array_values($rows) as $i => $row) {
            $paise = WalletService::toPaise((string) $row['amount']);
            $due = Carbon::parse($row['due_on'])->startOfDay();

            if ($paise <= 0) {
                throw new DomainRuleException('Every installment needs an amount greater than zero.');
            }

            if ($previous && $due->lt($previous)) {
                throw new DomainRuleException('Installment due dates must be in order.');
            }

            $total += $paise;
            $previous = $due;
            $clean[] = ['sequence' => $i + 1, 'due_on' => $due->toDateString(), 'amount' => WalletService::fromPaise($paise)];
        }

        DB::transaction(function () use ($enrollment, $clean, $total, $by) {
            $locked = Enrollment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($enrollment->id);
            $net = WalletService::toPaise((string) $locked->net_fee);

            if ($total !== $net) {
                throw new DomainRuleException(sprintf('Installments add up to ₹%s but the course fee is ₹%s.',
                    WalletService::fromPaise($total), WalletService::fromPaise($net)));
            }

            $locked->installments()->delete();

            foreach ($clean as $row) {
                $locked->installments()->create([...$row, 'institute_id' => $locked->institute_id]);
            }

            activity()->causedBy($by)->performedOn($locked)->event('fees.plan_set')->log('Fee plan saved');
        });
    }

    /**
     * Installments with derived status: payments are applied to the earliest installments first.
     *
     * @return Collection<int, array{sequence: int, due_on: Carbon, amount: string, paid: string, status: InstallmentStatus}>
     */
    public function plan(Enrollment $enrollment): Collection
    {
        $rows = $enrollment->installments()->get();
        $rows = $rows->isNotEmpty()
            ? $rows->map(fn ($i) => ['sequence' => $i->sequence, 'due_on' => $i->due_on, 'amount' => (string) $i->amount])
            : collect([['sequence' => 1, 'due_on' => $enrollment->admission_date, 'amount' => (string) $enrollment->net_fee]]);

        $remaining = $this->paidPaise($enrollment);
        $today = now()->startOfDay();

        return $rows->map(function (array $row) use (&$remaining, $today) {
            $amount = WalletService::toPaise($row['amount']);
            $applied = min($amount, max($remaining, 0));
            $remaining -= $applied;

            $status = match (true) {
                $applied === $amount => InstallmentStatus::Paid,
                $row['due_on']->lt($today) => InstallmentStatus::Overdue,
                $applied > 0 => InstallmentStatus::Partial,
                default => InstallmentStatus::Upcoming,
            };

            return [...$row, 'amount' => WalletService::fromPaise($amount), 'paid' => WalletService::fromPaise($applied), 'status' => $status];
        })->values();
    }

    public function collect(Enrollment $enrollment, string $amount, PaymentMode|string $mode, ?string $reference, string $paidOn, InstituteUser|Admin $by, ?string $remarks = null): FeePayment
    {
        $paise = WalletService::toPaise($amount);

        if ($paise <= 0) {
            throw new DomainRuleException('Enter an amount greater than zero with at most two decimals.');
        }

        if ($by instanceof InstituteUser && (! $by->can('collect_fee') || $by->institute_id !== $enrollment->institute_id)) {
            throw new DomainRuleException('You are not allowed to collect fees for this enrollment.');
        }

        if (Carbon::parse($paidOn)->isAfter(now()->endOfDay())) {
            throw new DomainRuleException('The payment date cannot be in the future.');
        }

        return DB::transaction(function () use ($enrollment, $paise, $mode, $reference, $paidOn, $by, $remarks) {
            // Lock the enrollment so two counters cannot collect the same due at once.
            $locked = Enrollment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($enrollment->id);

            if (! in_array($locked->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true)) {
                throw new DomainRuleException("Fees can only be collected for approved enrollments (this one is {$locked->status->label()}).");
            }

            $due = WalletService::toPaise((string) $locked->net_fee) - $this->paidPaise($locked);

            if ($paise > $due) {
                throw new DomainRuleException('The amount is more than the due of ₹'.WalletService::fromPaise(max($due, 0)).'.');
            }

            $institute = $locked->student()->withoutGlobalScopes()->first()->institute;
            $yy = Carbon::parse($paidOn)->format('y');

            return FeePayment::create([
                'institute_id' => $locked->institute_id,
                'receipt_no' => sprintf('%s/R/%s/%05d', $institute->code, $yy, $this->sequences->next("receipt:{$locked->institute_id}:{$yy}")),
                'student_id' => $locked->student_id,
                'enrollment_id' => $locked->id,
                'amount' => WalletService::fromPaise($paise),
                'mode' => $mode,
                'reference_no' => $reference,
                'paid_on' => $paidOn,
                'received_by_user_id' => $by instanceof InstituteUser ? $by->id : null,
                'received_by_admin_id' => $by instanceof Admin ? $by->id : null,
                'status' => FeePaymentStatus::Valid,
                'remarks' => $remarks,
            ]);
        });
    }

    public function cancel(FeePayment $payment, string $reason, InstituteUser|Admin $by): void
    {
        if (trim($reason) === '') {
            throw new DomainRuleException('A reason is required to cancel a receipt.');
        }

        if ($by instanceof InstituteUser && (! $by->can('cancel_receipt') || $by->institute_id !== $payment->institute_id)) {
            throw new DomainRuleException('You are not allowed to cancel this receipt.');
        }

        DB::transaction(function () use ($payment, $reason, $by) {
            $locked = FeePayment::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->id);

            if (! $locked->isValid()) {
                throw new DomainRuleException("Receipt {$locked->receipt_no} is already cancelled.");
            }

            $locked->update([
                'status' => FeePaymentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_type' => $by->getMorphClass(),
                'cancelled_by_id' => $by->getKey(),
                'cancel_reason' => $reason,
            ]);

            activity()->causedBy($by)->performedOn($locked)->event('fees.receipt_cancelled')->log("Receipt {$locked->receipt_no} cancelled: {$reason}");
        });

        $payment->refresh();
    }

    /** @return array{net: string, paid: string, due: string} */
    public function summary(Enrollment $enrollment): array
    {
        $net = WalletService::toPaise((string) $enrollment->net_fee);
        $paid = $this->paidPaise($enrollment);

        return ['net' => WalletService::fromPaise($net), 'paid' => WalletService::fromPaise($paid), 'due' => WalletService::fromPaise(max($net - $paid, 0))];
    }

    private function paidPaise(Enrollment $enrollment): int
    {
        return (int) FeePayment::query()->withoutGlobalScopes()
            ->where('enrollment_id', $enrollment->id)
            ->where('status', FeePaymentStatus::Valid)
            ->get(['amount'])
            ->sum(fn (FeePayment $p) => WalletService::toPaise((string) $p->amount));
    }
}
