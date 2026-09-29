<?php

namespace App\Filament\Admin\Resources\Certificates;

use App\Enums\CertificateStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use App\Models\Institute;
use App\Services\CertificateService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Exams';

    public static function table(Table $table): Table
    {
        return self::certificatesTable($table, admin: true);
    }

    /** Shared with the institute panel (read-only there). */
    public static function certificatesTable(Table $table, bool $admin): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['enrollment.student', 'enrollment.course', 'institute']))
            ->defaultSort('id', 'desc')
            ->columns(array_values(array_filter([
                TextColumn::make('certificate_no')->searchable()->copyable(),
                TextColumn::make('enrollment.student.registration_no')->label('Reg. no.')->searchable(),
                TextColumn::make('student')->state(fn (Certificate $r) => $r->enrollment?->student?->full_name),
                TextColumn::make('enrollment.course.code')->label('Course'),
                $admin ? TextColumn::make('institute.code')->label('Institute')->description(fn (Certificate $r) => $r->institute?->name) : null,
                TextColumn::make('issued_on')->date()->sortable(),
                TextColumn::make('status')->badge()->color(fn (CertificateStatus $state) => $state === CertificateStatus::Issued ? 'success' : 'danger'),
                TextColumn::make('revoked_reason')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])))
            ->filters(array_values(array_filter([
                SelectFilter::make('status')->options(CertificateStatus::class),
                $admin ? SelectFilter::make('institute_id')->label('Institute')->searchable()
                    ->options(fn () => Institute::query()->orderBy('code')->get()->mapWithKeys(fn (Institute $i) => [$i->id => "{$i->code} — {$i->name}"])) : null,
            ])))
            ->recordActions(array_values(array_filter([
                Action::make('download')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Certificate $r) => route('documents.certificate', $r), shouldOpenInNewTab: true),
                Action::make('verify')->label('Verify page')->icon(Heroicon::OutlinedShieldCheck)->color('gray')
                    ->url(fn (Certificate $r) => $r->verificationUrl(), shouldOpenInNewTab: true),
                $admin ? Action::make('revoke')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Certificate $r) => $r->isValid() && auth('admin')->user()?->can('revoke', $r))
                    ->modalDescription('The verification page will show this certificate as revoked.')
                    ->schema([Textarea::make('reason')->required()->maxLength(255)])
                    ->action(function (Certificate $r, array $data, Action $action) {
                        try {
                            app(CertificateService::class)->revoke($r, $data['reason'], auth('admin')->user());
                        } catch (DomainRuleException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->halt();
                        }

                        Notification::make()->success()->title("Certificate {$r->certificate_no} revoked")->send();
                    }) : null,
            ])));
    }

    public static function getPages(): array
    {
        return ['index' => ListCertificates::route('/')];
    }
}
