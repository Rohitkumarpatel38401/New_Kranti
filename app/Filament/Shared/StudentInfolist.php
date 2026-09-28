<?php

namespace App\Filament\Shared;

use App\Enums\AddressType;
use App\Models\Student;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only student profile, used by the institute and HQ panels.
 */
class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Student')->columns(3)->schema([
                TextEntry::make('registration_no')->label('Registration no.')->copyable(),
                TextEntry::make('full_name')->label('Name'),
                TextEntry::make('status')->badge(),
                TextEntry::make('phone'),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('joined_on')->label('Admitted on')->date(),
                TextEntry::make('personal.dob')->label('Date of birth')->date(),
                TextEntry::make('personal.gender')->label('Gender'),
                TextEntry::make('personal.category')->label('Category')->placeholder('—'),
                TextEntry::make('personal.aadhaar_masked')->label('Aadhaar')->placeholder('—'),
                TextEntry::make('admission_source')->label('Source')->badge(),
                TextEntry::make('institute.name')->label('Institute'),
                TextEntry::make('address')->label('Address')->columnSpanFull()->placeholder('—')
                    ->state(fn (Student $record) => $record->address(AddressType::Permanent)?->oneLine()),
            ]),
            Section::make('Parents / guardians')->schema([
                RepeatableEntry::make('guardians')->hiddenLabel()->columns(4)->schema([
                    TextEntry::make('relation'),
                    TextEntry::make('name'),
                    TextEntry::make('phone')->placeholder('—'),
                    TextEntry::make('occupation')->placeholder('—'),
                ]),
            ]),
            Section::make('Previous education')->collapsed()->schema([
                RepeatableEntry::make('qualifications')->hiddenLabel()->columns(4)->placeholder('None added')->schema([
                    TextEntry::make('level'),
                    TextEntry::make('board_university')->placeholder('—'),
                    TextEntry::make('passing_year')->placeholder('—'),
                    TextEntry::make('score')->placeholder('—'),
                ]),
            ]),
        ]);
    }
}
