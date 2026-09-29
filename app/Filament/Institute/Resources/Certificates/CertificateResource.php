<?php

namespace App\Filament\Institute\Resources\Certificates;

use App\Filament\Admin\Resources\Certificates\CertificateResource as HqCertificates;
use App\Filament\Institute\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Certificates HQ issued to this institute's students (download only).
 */
class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static string|UnitEnum|null $navigationGroup = 'Exams';

    public static function table(Table $table): Table
    {
        return HqCertificates::certificatesTable($table, admin: false);
    }

    public static function getPages(): array
    {
        return ['index' => ListCertificates::route('/')];
    }
}
