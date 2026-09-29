<?php

namespace App\Filament\Admin\Resources\AttendanceSessions\Pages;

use App\Filament\Admin\Resources\AttendanceSessions\AttendanceSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceSessions extends ListRecords
{
    protected static string $resource = AttendanceSessionResource::class;
}
