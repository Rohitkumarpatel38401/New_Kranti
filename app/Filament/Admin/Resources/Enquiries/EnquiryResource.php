<?php

namespace App\Filament\Admin\Resources\Enquiries;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\Gender;
use App\Enums\GuardianRelation;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Enquiries\Pages\ListEnquiries;
use App\Models\Enquiry;
use App\Services\EnquiryService;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class EnquiryResource extends Resource
{
    protected static ?string $model = Enquiry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Enquiries';

    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        $count = Enquiry::query()->where('status', EnquiryStatus::New)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('course'))
            ->columns([
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('name')->searchable()->description(fn (Enquiry $r) => $r->email),
                TextColumn::make('phone')->searchable()->copyable(),
                TextColumn::make('course.code')->label('Course')->placeholder('—'),
                TextColumn::make('message')->limit(60)->tooltip(fn (Enquiry $r) => $r->message)->placeholder('—')->wrap(),
                TextColumn::make('status')->badge()->color(fn (EnquiryStatus $state) => match ($state) {
                    EnquiryStatus::New => 'warning',
                    EnquiryStatus::Contacted => 'info',
                    EnquiryStatus::Converted => 'success',
                    EnquiryStatus::Closed => 'gray',
                }),
            ])
            ->filters([
                SelectFilter::make('type')->options(EnquiryType::class),
                SelectFilter::make('status')->options(EnquiryStatus::class),
            ])
            ->recordActions([
                Action::make('contacted')
                    ->label('Mark contacted')
                    ->icon(Heroicon::OutlinedPhone)
                    ->visible(fn (Enquiry $r) => $r->status === EnquiryStatus::New)
                    ->action(fn (Enquiry $r, Action $action) => self::run(
                        fn () => app(EnquiryService::class)->markContacted($r, auth('admin')->user()), 'Marked as contacted', $action)),
                Action::make('convert')
                    ->label('Convert to student')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('success')
                    ->visible(fn (Enquiry $r) => $r->isOpen())
                    ->modalDescription('Creates a student in the main institute with admission source "Website". Enrol them in a course from the student page.')
                    ->fillForm(fn (Enquiry $r) => [
                        'first_name' => Str::before($r->name, ' '),
                        'last_name' => Str::contains($r->name, ' ') ? Str::after($r->name, ' ') : null,
                        'phone' => $r->phone,
                        'email' => $r->email,
                        'guardian_relation' => GuardianRelation::Father->value,
                    ])
                    ->schema([
                        Section::make('Student')->columns(2)->schema([
                            TextInput::make('first_name')->required()->maxLength(100),
                            TextInput::make('last_name')->maxLength(100),
                            TextInput::make('phone')->tel()->required()->maxLength(20),
                            TextInput::make('email')->email()->maxLength(255),
                            DatePicker::make('dob')->label('Date of birth')->required()->maxDate(now()),
                            Select::make('gender')->options(Gender::class)->required(),
                        ]),
                        Section::make('Primary guardian')->columns(3)->schema([
                            Select::make('guardian_relation')->label('Relation')->options(GuardianRelation::class)->required(),
                            TextInput::make('guardian_name')->label('Name')->required()->maxLength(255),
                            TextInput::make('guardian_phone')->label('Phone')->tel()->maxLength(20),
                        ]),
                    ])
                    ->action(fn (Enquiry $r, array $data, Action $action) => self::run(
                        fn () => app(EnquiryService::class)->convertToStudent(
                            $r,
                            ['first_name' => $data['first_name'], 'last_name' => $data['last_name'] ?? null, 'phone' => $data['phone'], 'email' => $data['email'] ?? null],
                            ['dob' => $data['dob'], 'gender' => self::value($data['gender'])],
                            [[
                                'relation' => self::value($data['guardian_relation']),
                                'name' => $data['guardian_name'],
                                'phone' => $data['guardian_phone'] ?? null,
                                'is_primary' => true,
                            ]],
                            auth('admin')->user(),
                        ), 'Student created', $action)),
                Action::make('close')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Enquiry $r) => $r->isOpen())
                    ->action(fn (Enquiry $r, Action $action) => self::run(
                        fn () => app(EnquiryService::class)->close($r, auth('admin')->user()), 'Enquiry closed', $action)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEnquiries::route('/')];
    }

    /** Filament may hand a Select's state back as the enum case. */
    private static function value(mixed $state): mixed
    {
        return $state instanceof BackedEnum ? $state->value : $state;
    }

    private static function run(Closure $work, string $success, Action $action): void
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
