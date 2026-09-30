<?php

namespace App\Filament\Institute\Resources\Enrollments;

use App\Enums\EnrollmentStatus;
use App\Enums\PaymentMode;
use App\Enums\ResultStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Resources\Batches\BatchResource;
use App\Filament\Institute\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Institute\Resources\Enrollments\Pages\ListEnrollments;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Services\ResultService;
use App\Services\WalletService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class EnrollmentResource extends Resource
{
    protected static ?string $model = Enrollment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Admissions';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('student_id')->label('Student')->required()->searchable()
                ->getSearchResultsUsing(fn (string $search) => Student::query()
                    ->where(fn ($q) => $q->where('registration_no', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%"))
                    ->limit(50)->get()
                    ->mapWithKeys(fn (Student $s) => [$s->id => "{$s->registration_no} — {$s->full_name}"]))
                ->getOptionLabelUsing(fn ($value) => ($s = Student::find($value)) ? "{$s->registration_no} — {$s->full_name}" : null)
                // Tenant-scoped lookup: a student of another institute is "not found".
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                    if (! Student::query()->whereKey($value)->exists()) {
                        $fail('Choose one of your students.');
                    }
                }])
                ->default(fn () => request()->integer('student') ?: null),
            Select::make('course_id')->label('Course')->required()->live()
                ->options(fn () => BatchResource::courseOptions())
                ->afterStateUpdated(function (?string $state, Set $set) {
                    $set('batch_id', null);
                    $set('course_fee', $state ? Course::find($state)?->suggested_fee : null);
                }),
            Select::make('batch_id')->label('Batch')
                ->options(fn (Get $get) => Batch::query()->where('course_id', $get('course_id'))->pluck('name', 'id')),
            Select::make('academic_session_id')->label('Session')->required()
                ->options(fn () => AcademicSession::query()->orderByDesc('starts_on')->pluck('name', 'id'))
                ->default(fn () => AcademicSession::current()?->id),
            DatePicker::make('admission_date')->default(now())->maxDate(now())->required(),
            TextInput::make('course_fee')->label('Course fee (₹)')->required()->numeric()->minValue(0),
            TextInput::make('discount')->label('Discount (₹)')->numeric()->minValue(0)->default(0)
                ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                    if ((float) $value > (float) $get('course_fee')) {
                        $fail('The discount cannot be more than the course fee.');
                    }
                }]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['student', 'course', 'batch', 'result']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('enrollment_no')->label('Enrollment no.')->placeholder('Not yet issued')->searchable(),
                TextColumn::make('student.registration_no')->label('Reg. no.')->searchable(),
                TextColumn::make('student.first_name')->label('Student')
                    ->formatStateUsing(fn (Enrollment $record) => $record->student?->full_name)->searchable(['first_name', 'last_name']),
                TextColumn::make('course.code')->label('Course'),
                TextColumn::make('batch.name')->label('Batch')->placeholder('—'),
                TextColumn::make('net_fee')->label('Fee')->money('INR'),
                TextColumn::make('status')->badge(),
                TextColumn::make('rejection_reason')->label('HQ remarks')->placeholder('—')->wrap()->toggleable(),
            ])
            ->filters([SelectFilter::make('status')->options(EnrollmentStatus::class)])
            ->recordActions([
                ActionGroup::make([
                    Action::make('idCard')->label('ID card')->icon(Heroicon::OutlinedIdentification)
                        ->visible(fn (Enrollment $record) => in_array($record->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true))
                        ->url(fn (Enrollment $record) => route('documents.id-card', $record), shouldOpenInNewTab: true),
                    Action::make('admitCard')->label('Admit card')->icon(Heroicon::OutlinedTicket)
                        ->visible(fn (Enrollment $record) => $record->status === EnrollmentStatus::Active)
                        ->url(fn (Enrollment $record) => route('documents.admit-card', $record), shouldOpenInNewTab: true),
                    Action::make('marksheet')->label('Marksheet')->icon(Heroicon::OutlinedDocumentText)
                        ->visible(fn (Enrollment $record) => $record->result?->status === ResultStatus::Published)
                        ->url(fn (Enrollment $record) => route('documents.marksheet', $record->result), shouldOpenInNewTab: true),
                ])->label('Documents')->icon(Heroicon::OutlinedPrinter)->color('gray'),
                Action::make('enterMarks')
                    ->label('Enter marks')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->visible(fn (Enrollment $record) => $record->status === EnrollmentStatus::Active
                        && (bool) auth('institute')->user()?->can('enter_marks')
                        && ($record->result === null || $record->result->isEditable()))
                    ->modalDescription(fn (Enrollment $record) => $record->result?->return_remarks ? 'HQ remarks: '.$record->result->return_remarks : null)
                    ->fillForm(function (Enrollment $record) {
                        $saved = $record->result?->marks->keyBy('course_subject_id');

                        return [
                            'exam_date' => $record->result?->exam_date?->toDateString() ?? now()->toDateString(),
                            'marks' => $record->course->courseSubjects->mapWithKeys(fn ($cs) => [$cs->id => [
                                'theory' => $saved?->get($cs->id)?->theory,
                                'practical' => $saved?->get($cs->id)?->practical,
                                'absent' => (bool) $saved?->get($cs->id)?->is_absent,
                            ]])->all(),
                        ];
                    })
                    ->schema(fn (Enrollment $record) => [
                        DatePicker::make('exam_date')->required()->maxDate(now()),
                        ...$record->course->courseSubjects()->with('subject')->get()->map(fn ($cs) => Fieldset::make("{$cs->subject?->code} — {$cs->subject?->localized('name')}")
                            ->columns(3)
                            ->schema([
                                TextInput::make("marks.{$cs->id}.theory")->label("Theory (max {$cs->theory_max})")->integer()->minValue(0)->maxValue($cs->theory_max),
                                TextInput::make("marks.{$cs->id}.practical")->label("Practical (max {$cs->practical_max})")->integer()->minValue(0)->maxValue($cs->practical_max),
                                Toggle::make("marks.{$cs->id}.absent")->label('Absent')->inline(false),
                            ]))->all(),
                    ])
                    ->action(fn (Enrollment $record, array $data, Action $action) => self::run($action, 'Marks saved as draft',
                        fn () => app(ResultService::class)->saveMarks($record, $data['marks'], (string) $data['exam_date'], auth('institute')->user()))),
                Action::make('collectFee')
                    ->label('Collect fee')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Enrollment $record) => in_array($record->status, [EnrollmentStatus::Active, EnrollmentStatus::Completed], true)
                        && (bool) auth('institute')->user()?->can('create', FeePayment::class))
                    ->modalDescription(function (Enrollment $record) {
                        $s = app(FeeService::class)->summary($record);

                        return "Course fee ₹{$s['net']} · paid ₹{$s['paid']} · due ₹{$s['due']}";
                    })
                    ->schema([
                        TextInput::make('amount')->label('Amount (₹)')->required()->numeric()->minValue(0.01)->regex('/^\d{1,8}(\.\d{1,2})?$/')->validationMessages(['regex' => 'Enter rupees with at most two decimals.'])
                            ->maxValue(fn (Enrollment $record) => (float) app(FeeService::class)->summary($record)['due']),
                        Select::make('mode')->options(PaymentMode::class)->required()->default(PaymentMode::Cash),
                        TextInput::make('reference_no')->label('Reference / UTR')->maxLength(60),
                        DatePicker::make('paid_on')->required()->default(now())->maxDate(now()),
                    ])
                    ->action(function (Enrollment $record, array $data, Action $action) {
                        try {
                            $payment = app(FeeService::class)->collect($record, (string) $data['amount'], // validated: exact rupees.paise
                                $data['mode'], $data['reference_no'] ?? null, (string) $data['paid_on'], auth('institute')->user());
                        } catch (DomainRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title("Receipt {$payment->receipt_no} issued")
                            ->actions([Action::make('print')->label('Print receipt')
                                ->url(route('receipts.show', $payment), shouldOpenInNewTab: true)])
                            ->send();
                    }),
                Action::make('feePlan')
                    ->label('Fee plan')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('gray')
                    ->visible(fn (Enrollment $record) => ! in_array($record->status, [EnrollmentStatus::Cancelled, EnrollmentStatus::Rejected], true)
                        && self::canManage($record))
                    ->modalDescription(fn (Enrollment $record) => "Installments must add up to the course fee of ₹{$record->net_fee}.")
                    ->fillForm(fn (Enrollment $record) => ['installments' => app(FeeService::class)->plan($record)
                        ->map(fn ($row) => ['due_on' => $row['due_on']->toDateString(), 'amount' => $row['amount']])->all()])
                    ->schema([
                        Repeater::make('installments')->columns(2)->minItems(1)->maxItems(24)->schema([
                            DatePicker::make('due_on')->required(),
                            TextInput::make('amount')->label('Amount (₹)')->required()->numeric()->minValue(0.01)->regex('/^\d{1,8}(\.\d{1,2})?$/')->validationMessages(['regex' => 'Enter rupees with at most two decimals.']),
                        ]),
                    ])
                    ->action(fn (Enrollment $record, array $data, Action $action) => self::run($action, 'Fee plan saved',
                        fn () => app(FeeService::class)->setPlan($record, array_map(
                            fn ($row) => ['due_on' => (string) $row['due_on'], 'amount' => (string) $row['amount']],
                            array_values($data['installments'])), auth('institute')->user()))),
                Action::make('submit')
                    ->label('Submit to HQ')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Enrollment $record) => self::submitDescription($record))
                    ->visible(fn (Enrollment $record) => $record->status === EnrollmentStatus::Draft && self::canManage($record))
                    ->action(fn (Enrollment $record, Action $action) => self::run($action, 'Sent to HQ for approval',
                        fn () => app(EnrollmentService::class)->submit($record, auth('institute')->user()))),
                Action::make('reopen')
                    ->label('Fix and resubmit')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn (Enrollment $record) => $record->status === EnrollmentStatus::Rejected && self::canManage($record))
                    ->action(fn (Enrollment $record, Action $action) => self::run($action, 'Moved back to draft',
                        fn () => app(EnrollmentService::class)->reopen($record, auth('institute')->user()))),
                Action::make('cancel')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Enrollment $record) => $record->status === EnrollmentStatus::Draft && self::canManage($record))
                    ->action(fn (Enrollment $record, Action $action) => self::run($action, 'Enrollment cancelled',
                        fn () => app(EnrollmentService::class)->cancel($record, auth('institute')->user()))),
                Action::make('drop')
                    ->label('Mark dropped')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->visible(fn (Enrollment $record) => $record->status === EnrollmentStatus::Active && self::canManage($record))
                    ->schema([Textarea::make('reason')->required()->maxLength(255)])
                    ->action(fn (Enrollment $record, array $data, Action $action) => self::run($action, 'Marked as dropped',
                        fn () => app(EnrollmentService::class)->drop($record, $data['reason'], auth('institute')->user()))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEnrollments::route('/'),
            'create' => CreateEnrollment::route('/create'),
        ];
    }

    private static function canManage(Enrollment $record): bool
    {
        return (bool) auth('institute')->user()?->can('update', $record);
    }

    private static function submitDescription(Enrollment $record): string
    {
        $institute = $record->student->institute;

        if ($institute->wallet_exempt) {
            return 'This enrollment will be sent to HQ for approval.';
        }

        try {
            $fee = $institute->hqFeeFor($record->course);
        } catch (DomainRuleException $e) {
            return $e->getMessage();
        }

        return "₹{$fee} HQ fee will be debited from your wallet (balance ₹".app(WalletService::class)->balance($institute).'). It is refunded if HQ rejects the enrollment.';
    }

    private static function run(Action $action, string $success, Closure $work): void
    {
        try {
            $work();
        } catch (DomainRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->halt();
        }

        Notification::make()->success()->title($success)->send();
    }
}
