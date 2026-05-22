<?php

declare(strict_types=1);

use App\DTOs\ErrorCode;

it('exposes the correct backing string for each case', function () {
    expect(ErrorCode::B6->value)->toBe('B6');
    expect(ErrorCode::B8->value)->toBe('B8');
    expect(ErrorCode::B9->value)->toBe('B9');
    expect(ErrorCode::B12->value)->toBe('B12');
    expect(ErrorCode::C1->value)->toBe('C1');
    expect(ErrorCode::C3->value)->toBe('C3');
    expect(ErrorCode::C8->value)->toBe('C8');
    expect(ErrorCode::G3->value)->toBe('G3');
    expect(ErrorCode::G4->value)->toBe('G4');
    expect(ErrorCode::H1->value)->toBe('H1');
    expect(ErrorCode::None->value)->toBe('NONE');
});

it('builds a case from its backing string', function () {
    expect(ErrorCode::from('B6'))->toBe(ErrorCode::B6);
    expect(ErrorCode::from('B12'))->toBe(ErrorCode::B12);
    expect(ErrorCode::from('NONE'))->toBe(ErrorCode::None);
});

it('throws ValueError for an unknown string', function () {
    ErrorCode::from('Invalid');
})->throws(ValueError::class);

it('exposes exactly eleven cases', function () {
    expect(ErrorCode::cases())->toHaveCount(11);
});
