<?php

it('boots with KCTI configuration', function () {
    expect(config('app.name'))->toBe('Kranti Computer Training Institute')
        ->and(config('app.timezone'))->toBe('Asia/Kolkata')
        ->and(config('database.default'))->toBe('sqlite');
});
