<?php

use App\Domain\Shared\ValueObjects\Money;

it('adds decimals exactly', fn () => expect((string) Money::of('0.10')->add(Money::of('0.20')))->toBe('0.30'));
it('supports the full database precision', fn () => expect((string) Money::of('9999999999999.99'))->toBe('9999999999999.99'));
it('rejects invalid money inputs', fn ($input) => Money::of($input))
    ->with([0.1, '1.001', '1e3', 'NaN', '', true, null])->throws(InvalidArgumentException::class);
it('detects overflow', fn () => Money::of('9999999999999.99')->add(Money::of('0.01')))->throws(OverflowException::class);
it('rounds half away from zero', fn ($input, $expected) => expect((string) Money::rounded($input))->toBe($expected))
    ->with([['1.005', '1.01'], ['1.0049', '1.00'], ['-1.005', '-1.01'], ['-0.004', '0.00']]);

it('checks positive, negative, and zero amounts correctly', function () {
    expect(Money::of('10.00')->isPositive())->toBeTrue()
        ->and(Money::of('10.00')->isNegative())->toBeFalse()
        ->and(Money::of('10.00')->isZero())->toBeFalse()
        ->and(Money::of('-5.50')->isPositive())->toBeFalse()
        ->and(Money::of('-5.50')->isNegative())->toBeTrue()
        ->and(Money::of('-5.50')->isZero())->toBeFalse()
        ->and(Money::of('0.00')->isPositive())->toBeFalse()
        ->and(Money::of('0.00')->isNegative())->toBeFalse()
        ->and(Money::of('0.00')->isZero())->toBeTrue();
});
