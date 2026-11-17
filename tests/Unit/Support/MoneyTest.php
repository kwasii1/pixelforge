<?php

use App\Support\Money;

it('parses decimal dollar strings into nanodollars', function (string $amount, int $expected) {
    expect(Money::fromDecimal($amount))->toBe($expected);
})->with([
    'whole dollars' => ['5', 5_000_000_000],
    'two places' => ['12.50', 12_500_000_000],
    'nine places' => ['0.046125000', 46_125_000],
    'one nanodollar' => ['0.000000001', 1],
    'negative' => ['-1.5', -1_500_000_000],
]);

it('rejects amounts that are not exact decimals with at most nine places', function (string $amount) {
    Money::fromDecimal($amount);
})->throws(InvalidArgumentException::class)->with(['1e-9', '0.0000000001', 'abc', '1.2.3', '']);

it('renders nanodollars as an exact nine-place decimal', function (int $nanodollars, string $expected) {
    expect(Money::toDecimal($nanodollars))->toBe($expected);
})->with([
    [373_875_000, '0.373875000'],
    [0, '0.000000000'],
    [12_500_000_001, '12.500000001'],
    [-46_125_000, '-0.046125000'],
]);

it('formats nanodollars for display, rounding half up', function (int $nanodollars, string $expected) {
    expect(Money::format($nanodollars))->toBe($expected);
})->with([
    [467_343_750, '$0.4673'],
    [305_510_000, '$0.3055'],
    [50_000, '$0.0001'],
    [0, '$0.0000'],
    [1_234_567_000_000, '$1,234.5670'],
]);

it('never shows a non-zero amount as free', function () {
    expect(Money::format(49_999))->toBe('<$0.0001');
});

it('formats with a chosen number of decimals', function () {
    expect(Money::format(10_000_000_000, 2))->toBe('$10.00');
});
