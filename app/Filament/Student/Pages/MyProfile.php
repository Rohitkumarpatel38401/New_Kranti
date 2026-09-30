<?php

namespace App\Filament\Student\Pages;

use App\Enums\AddressType;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only profile. Aadhaar, phone numbers of guardians and other sensitive details are not shown.
 */
class MyProfile extends Page
{
    protected string $view = 'filament.student.pages.my-profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?int $navigationSort = 0;

    protected static ?string $slug = 'my-profile';

    public static function getNavigationLabel(): string
    {
        return __('portal.profile.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('portal.profile.title');
    }

    protected function getViewData(): array
    {
        $student = auth('student')->user()->load(['personal', 'guardians', 'institute', 'addresses']);
        $photo = $student->photo_path && Storage::disk('local')->exists($student->photo_path)
            ? 'data:'.Storage::disk('local')->mimeType($student->photo_path).';base64,'.base64_encode(Storage::disk('local')->get($student->photo_path))
            : null;

        return [
            'student' => $student,
            'photo' => $photo,
            'address' => $student->address(AddressType::Permanent),
        ];
    }
}
