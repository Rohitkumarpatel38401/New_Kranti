<?php

namespace App\Filament\Institute\Resources\AttendanceSessions\Pages;

use App\Filament\Institute\Resources\AttendanceSessions\AttendanceSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;
}
