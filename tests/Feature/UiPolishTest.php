<?php

use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;

it('makes every panel select and select filter searchable by default', function () {
    expect(Select::make('anything')->options(['a' => 'A'])->isSearchable())->toBeTrue()
        ->and(SelectFilter::make('anything')->options(['a' => 'A'])->getSearchable())->toBeTrue();
});

it('loads the new website font and the scroll-reveal hook before paint', function () {
    $this->get(route('site.home'))
        ->assertOk()
        ->assertSee('family=Poppins', false)
        ->assertSee("classList.add('js-reveal')", false);
});

it('turns the website course dropdown into a searchable select', function () {
    $this->get(route('site.students.registration'))
        ->assertOk()
        ->assertSee('data-searchable', false)
        ->assertSee('tom-select', false);
});
