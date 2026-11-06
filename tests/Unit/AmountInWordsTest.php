<?php

use App\Domain\Shared\Support\AmountInWords;
use App\Domain\Shared\ValueObjects\Money;

it('spells out amounts using Indian crore-lakh-thousand grouping', function ($amount, $expected) {
    expect(AmountInWords::inr(Money::of($amount)))->toBe($expected);
})->with([
    ['0.00', 'Rupees Zero Only'],
    ['1.00', 'Rupees One Only'],
    ['9.00', 'Rupees Nine Only'],
    ['10.00', 'Rupees Ten Only'],
    ['21.00', 'Rupees Twenty One Only'],
    ['100.00', 'Rupees One Hundred Only'],
    ['1180.00', 'Rupees One Thousand One Hundred Eighty Only'],
    ['12345.00', 'Rupees Twelve Thousand Three Hundred Forty Five Only'],
    ['100000.00', 'Rupees One Lakh Only'],
    ['150000.00', 'Rupees One Lakh Fifty Thousand Only'],
    ['1234567.00', 'Rupees Twelve Lakh Thirty Four Thousand Five Hundred Sixty Seven Only'],
    ['10000000.00', 'Rupees One Crore Only'],
    ['123456789.00', 'Rupees Twelve Crore Thirty Four Lakh Fifty Six Thousand Seven Hundred Eighty Nine Only'],
    // Magnitudes above crore fold into the crore count.
    ['10000000000.00', 'Rupees One Thousand Crore Only'],
    ['9999999999999.99', 'Rupees Nine Lakh Ninety Nine Thousand Nine Hundred Ninety Nine Crore '
        .'Ninety Nine Lakh Ninety Nine Thousand Nine Hundred Ninety Nine and Paise Ninety Nine Only'],
]);

it('spells out paise separately, including zero rupees', function ($amount, $expected) {
    expect(AmountInWords::inr(Money::of($amount)))->toBe($expected);
})->with([
    ['0.05', 'Rupees Zero and Paise Five Only'],
    ['0.50', 'Rupees Zero and Paise Fifty Only'],
    ['123.45', 'Rupees One Hundred Twenty Three and Paise Forty Five Only'],
    ['0.01', 'Rupees Zero and Paise One Only'],
    ['1.10', 'Rupees One and Paise Ten Only'],
    ['1000000.99', 'Rupees Ten Lakh and Paise Ninety Nine Only'],
]);

it('refuses negative amounts', function () {
    AmountInWords::inr(Money::of('-1.00'));
})->throws(InvalidArgumentException::class);
