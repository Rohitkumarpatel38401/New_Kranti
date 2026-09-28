<?php

use App\Services\NumberSequenceService;
use Illuminate\Support\Facades\DB;

it('starts a new key at 1 and increments', function () {
    $seq = app(NumberSequenceService::class);

    expect($seq->next('receipt:1'))->toBe(1)
        ->and($seq->next('receipt:1'))->toBe(2)
        ->and($seq->next('receipt:2'))->toBe(1)
        ->and($seq->peek('receipt:1'))->toBe(2)
        ->and($seq->peek('missing'))->toBe(0);
});

it('formats institute codes', function () {
    $seq = app(NumberSequenceService::class);

    expect($seq->nextInstituteCode())->toBe('KCTI0001')
        ->and($seq->nextInstituteCode())->toBe('KCTI0002');
});

it('never lowers a sequence with syncAtLeast', function () {
    $seq = app(NumberSequenceService::class);
    $seq->syncAtLeast('institute_code', 5);
    $seq->syncAtLeast('institute_code', 3);

    expect($seq->nextInstituteCode())->toBe('KCTI0006');
});

it('produces unique values across many calls inside nested transactions', function () {
    $seq = app(NumberSequenceService::class);

    $values = collect(range(1, 50))->map(fn () => DB::transaction(fn () => $seq->next('bulk')));

    expect($values->unique()->count())->toBe(50)->and($values->max())->toBe(50);
});
