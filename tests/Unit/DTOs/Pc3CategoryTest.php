<?php

declare(strict_types=1);

use App\DTOs\Pc3Category;

it('exposes the correct backing string for each case', function () {
    expect(Pc3Category::Predicate->value)->toBe('Predicate');
    expect(Pc3Category::Concept->value)->toBe('Concept');
    expect(Pc3Category::Context->value)->toBe('Context');
});

it('builds a case from its backing string', function () {
    expect(Pc3Category::from('Predicate'))->toBe(Pc3Category::Predicate);
    expect(Pc3Category::from('Concept'))->toBe(Pc3Category::Concept);
    expect(Pc3Category::from('Context'))->toBe(Pc3Category::Context);
});

it('throws ValueError for an unknown string', function () {
    Pc3Category::from('Invalid');
})->throws(ValueError::class);

it('exposes exactly three cases in declared order', function () {
    $cases = Pc3Category::cases();
    expect($cases)->toHaveCount(3);
    expect($cases[0])->toBe(Pc3Category::Predicate);
    expect($cases[1])->toBe(Pc3Category::Concept);
    expect($cases[2])->toBe(Pc3Category::Context);
});
