<?php
use App\Domain\Gst\Services\{GstCalculator, PlaceOfSupplyResolver};
use App\Domain\Shared\ValueObjects\Money;

function gstLine(array $options = []) {
    $args = array_merge([
        'unitPrice' => Money::of('1000'), 'quantity' => '1', 'rate' => '18',
        'supply' => (new PlaceOfSupplyResolver)->resolve('27', '27'),
    ], $options);
    return (new GstCalculator)->calculate(...$args);
}

it('splits intra-state tax at every requested slab', function ($rate, $half) {
    $r = gstLine(['rate' => $rate]);
    expect((string) $r->cgst)->toBe($half)->and((string) $r->sgst)->toBe($half)
        ->and((string) $r->igst)->toBe('0.00');
})->with([['0', '0.00'], ['5', '25.00'], ['12', '60.00'], ['18', '90.00'], ['28', '140.00']]);

it('calculates inter-state IGST at every requested slab', function ($rate, $tax) {
    $r = gstLine(['rate' => $rate, 'supply' => (new PlaceOfSupplyResolver)->resolve('27', '24')]);
    expect((string) $r->igst)->toBe($tax)->and((string) $r->cgst)->toBe('0.00')
        ->and((string) $r->sgst)->toBe('0.00');
})->with([['0', '0.00'], ['5', '50.00'], ['12', '120.00'], ['18', '180.00'], ['28', '280.00']]);

it('supports verified rates outside the historical slab list', function () {
    $r = gstLine(['rate' => '40']);
    expect((string) $r->total)->toBe('1400.00');
});

it('calculates export and SEZ by explicit route', function ($type, $destination, $mode, $expected) {
    $r = gstLine(['supply' => (new PlaceOfSupplyResolver)->resolve('27', $destination, $type),
        'zeroRatedMode' => $mode, 'zeroRatedEligible' => true, 'sezAuthorized' => true]);
    expect((string) $r->igst)->toBe($expected)->and((string) $r->cgst)->toBe('0.00');
})->with([
    ['export', '99', 'lut', '0.00'], ['export', '99', 'with_payment', '180.00'],
    ['sez', '27', 'lut', '0.00'], ['sez', '27', 'with_payment', '180.00'],
    ['sez', '24', 'lut', '0.00'], ['sez', '24', 'with_payment', '180.00'],
]);

it('requires verified export eligibility', fn () => gstLine([
    'supply' => (new PlaceOfSupplyResolver)->resolve('27', '99', 'export'), 'zeroRatedMode' => 'lut',
]))->throws(InvalidArgumentException::class);

it('requires authorised SEZ operations', fn () => gstLine([
    'supply' => (new PlaceOfSupplyResolver)->resolve('27', '27', 'sez'),
    'zeroRatedMode' => 'lut', 'zeroRatedEligible' => true,
]))->throws(InvalidArgumentException::class);

it('returns recipient RCM liability without collecting supplier output tax', function ($destination) {
    $r = gstLine(['supply' => (new PlaceOfSupplyResolver)->resolve('27', $destination), 'reverseCharge' => true]);
    expect((string) $r->total)->toBe('1000.00')->and((string) $r->recipientTax)->toBe('180.00')
        ->and((string) $r->cgst)->toBe('0.00')->and((string) $r->sgst)->toBe('0.00')->and((string) $r->igst)->toBe('0.00');
})->with(['27', '24']);

it('does not collect tax for composition or unregistered suppliers', function ($regime) {
    expect((string) gstLine(['regime' => $regime])->total)->toBe('1000.00');
})->with(['composition', 'unregistered']);

it('rejects inter-state composition supplies', fn () => gstLine([
    'regime' => 'composition', 'supply' => (new PlaceOfSupplyResolver)->resolve('27', '24'),
]))->throws(InvalidArgumentException::class);

it('preserves exempt nil-rated and non-GST classification', function ($category) {
    $r = gstLine(['category' => $category, 'rate' => '0']);
    expect($r->category)->toBe($category)->and((string) $r->total)->toBe('1000.00');
})->with(['exempt', 'nil_rated', 'non_gst']);

it('discounts the taxable base before calculating tax', function () {
    $r = gstLine(['discount' => Money::of('100')]);
    expect((string) $r->taxableAmount)->toBe('900.00')->and((string) $r->cgst)->toBe('81.00')
        ->and((string) $r->total)->toBe('1062.00');
});

it('supports full discounts', fn () => expect((string) gstLine(['discount' => Money::of('1000')])->total)->toBe('0.00'));
it('rejects excessive discounts', fn () => gstLine(['discount' => Money::of('1000.01')]))->throws(InvalidArgumentException::class);
it('rejects negative discounts', fn () => gstLine(['discount' => Money::of('-1')]))->throws(InvalidArgumentException::class);
it('rejects negative sales prices', fn () => gstLine(['unitPrice' => Money::of('-1')]))->throws(InvalidArgumentException::class);

it('rounds fractional quantities before taxing the line', function () {
    $r = gstLine(['unitPrice' => Money::of('10.01'), 'quantity' => '1.005']);
    expect((string) $r->taxableAmount)->toBe('10.06')->and((string) $r->total)->toBe('11.88');
});

it('rounds tax components independently at half a paise', function () {
    $r = gstLine(['unitPrice' => Money::of('0.10'), 'rate' => '10']);
    expect((string) $r->cgst)->toBe('0.01')->and((string) $r->sgst)->toBe('0.01')->and((string) $r->total)->toBe('0.12');
});

it('keeps multi-line invoice totals stable by summing independently rounded lines', function () {
    $calculator = new GstCalculator;
    $supply = (new PlaceOfSupplyResolver)->resolve('27', '27');
    $lines = array_map(fn () => $calculator->calculate(Money::of('0.10'), '1', '10', $supply), range(1, 3));
    $cgst = array_reduce($lines, fn (Money $sum, $line) => $sum->add($line->cgst), Money::of('0'));
    $sgst = array_reduce($lines, fn (Money $sum, $line) => $sum->add($line->sgst), Money::of('0'));
    $total = array_reduce($lines, fn (Money $sum, $line) => $sum->add($line->total), Money::of('0'));
    expect((string) $cgst)->toBe('0.03')->and((string) $sgst)->toBe('0.03')
        ->and((string) $total)->toBe('0.36')->and((string) $cgst->add($sgst))->toBe('0.06');
});

it('keeps invoice paise distinct from statutory whole-rupee rounding', function ($value, $rounded) {
    expect((string) (new GstCalculator)->roundStatutoryPayable(Money::of($value)))->toBe($rounded);
})->with([['100.49', '100.00'], ['100.50', '101.00'], ['100.99', '101.00'], ['0.49', '0.00']]);

it('uses CGST and UTGST for applicable union territories', function ($state) {
    $r = gstLine(['supply' => (new PlaceOfSupplyResolver)->resolve($state, $state)]);
    expect((string) $r->utgst)->toBe('90.00')->and((string) $r->sgst)->toBe('0.00');
})->with(['04', '26', '31', '35', '38']);

it('calculates cess only on regular taxable supplies and routes RCM cess to recipient', function () {
    $resolver = new PlaceOfSupplyResolver;
    $calculator = new GstCalculator;
    $intra = $calculator->calculate(Money::of('100'), '1', '18', $resolver->resolve('27', '27'), cessRate: '12.5');
    $inter = $calculator->calculate(Money::of('100'), '1', '18', $resolver->resolve('27', '24'), cessRate: '12.5');
    $rcm = $calculator->calculate(Money::of('100'), '1', '18', $resolver->resolve('27', '24'), reverseCharge: true, cessRate: '12.5');
    $composition = $calculator->calculate(Money::of('100'), '1', '18', $resolver->resolve('27', '27'), regime: 'composition', cessRate: '12.5');
    $lut = $calculator->calculate(Money::of('100'), '1', '18', $resolver->resolve('27', '99', 'export'), zeroRatedMode: 'lut', zeroRatedEligible: true, cessRate: '12.5');
    expect((string) $intra->cess)->toBe('12.50')->and((string) $intra->total)->toBe('130.50')
        ->and((string) $inter->igst)->toBe('18.00')->and((string) $inter->cess)->toBe('12.50')
        ->and((string) $rcm->recipientTax)->toBe('30.50')->and((string) $rcm->recipientCess)->toBe('12.50')
        ->and((string) $composition->cess)->toBe('0.00')->and((string) $lut->cess)->toBe('0.00');
});

it('rounds cess half-up to paise', function () {
    $r = gstLine(['unitPrice' => Money::of('0.10'), 'rate' => '0', 'cessRate' => '5']);
    expect((string) $r->cess)->toBe('0.01')->and((string) $r->total)->toBe('0.11');
});

it('rejects malformed quantities', fn ($q) => gstLine(['quantity' => $q]))
    ->with(['0', '-1', '1.0001', '1e2', 'abc'])->throws(InvalidArgumentException::class);
it('rejects malformed rates', fn ($r) => gstLine(['rate' => $r]))
    ->with(['-1', '100.001', '1.0001', 'abc'])->throws(InvalidArgumentException::class);
it('rejects an exemption with a nonzero rate', fn () => gstLine(['category' => 'exempt']))->throws(InvalidArgumentException::class);
it('rejects domestic LUT classification', fn () => gstLine(['zeroRatedMode' => 'lut']))->throws(InvalidArgumentException::class);
it('rejects contradictory RCM and zero-rated treatment', fn () => gstLine([
    'supply' => (new PlaceOfSupplyResolver)->resolve('27', '99', 'export'), 'zeroRatedMode' => 'lut',
    'zeroRatedEligible' => true, 'reverseCharge' => true,
]))->throws(InvalidArgumentException::class);
