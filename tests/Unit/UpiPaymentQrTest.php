<?php

use App\Domain\Shared\Support\UpiPaymentQr;
use App\Domain\Shared\ValueObjects\Money;

it('validates UPI virtual payment addresses', function ($vpa, $expected) {
    expect(UpiPaymentQr::isValidVpa($vpa))->toBe($expected);
})->with([
    ['name@bank', true],
    ['user.name-1_ok@pspbank', true],
    ['merchant@upi', true],
    ['@bank', false],
    ['name@', false],
    ['name bank@upi', false],
    ['name@up i', false],
    ['', false],
]);

it('builds a UPI deep link with payee, amount and note', function () {
    $uri = UpiPaymentQr::uri('shop@bank', 'ACME Traders', Money::of('1180.00'), 'Invoice SI/1');
    expect($uri)->toBe('upi://pay?pa=shop%40bank&pn=ACME%20Traders&am=1180.00&cu=INR&tn=Invoice%20SI%2F1');
});

it('rejects invalid payment details', function () {
    UpiPaymentQr::uri('not-a-vpa', 'ACME', Money::of('10.00'), 'x');
})->throws(InvalidArgumentException::class);

it('refuses zero and negative amounts', function ($amount) {
    UpiPaymentQr::uri('shop@bank', 'ACME', Money::of($amount), 'x');
})->with([['0.00'], ['-1.00']])->throws(InvalidArgumentException::class);

it('renders a square boolean QR as an HTML table', function () {
    $html = UpiPaymentQr::html('upi://pay?pa=shop@bank&pn=ACME&am=100.00&cu=INR');
    expect($html)->toStartWith('<table style="border-collapse:collapse;border-spacing:0;">');
    // Count rows and confirm every row has the same number of cells.
    preg_match_all('/<tr>/', $html, $rows);
    preg_match_all('/<td /', $html, $cells);
    expect(count($rows[0]))->toBeGreaterThan(20)
        ->and(count($cells[0]) % count($rows[0]))->toBe(0)
        ->and(substr_count($html, 'background:#000000;'))->toBeGreaterThan(0);
});
