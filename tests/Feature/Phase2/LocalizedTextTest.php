<?php

use App\Filament\Shared\LocalizedFields;
use App\Models\Concerns\HasLocalizedText;
use Illuminate\Database\Eloquent\Model;

function localizedModel(?array $name): Model
{
    $model = new class extends Model
    {
        use HasLocalizedText;

        protected $casts = ['name' => 'array'];
    };
    $model->name = $name;

    return $model;
}

it('returns the current-locale value', function () {
    app()->setLocale('hi');

    expect(localizedModel(['en' => 'Computer', 'hi' => 'कंप्यूटर'])->localized('name'))->toBe('कंप्यूटर');
});

it('falls back to English when the locale value is missing or blank', function (?string $hi) {
    app()->setLocale('hi');

    expect(localizedModel(['en' => 'Computer', 'hi' => $hi])->localized('name'))->toBe('Computer');
})->with([null, '', '  ']);

it('accepts an explicit locale', function () {
    expect(localizedModel(['en' => 'Computer', 'hi' => 'कंप्यूटर'])->localized('name', 'hi'))->toBe('कंप्यूटर');
});

it('returns null for an empty column', function () {
    expect(localizedModel(null)->localized('name'))->toBeNull();
});

it('builds English and Hindi fields with English required', function () {
    [$en, $hi] = LocalizedFields::make('name', 'Name');

    expect($en->getName())->toBe('name.en')
        ->and($hi->getName())->toBe('name.hi')
        ->and($en->isRequired())->toBeTrue()
        ->and($hi->isRequired())->toBeFalse();
});
