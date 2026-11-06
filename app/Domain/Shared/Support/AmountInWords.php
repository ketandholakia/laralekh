<?php

declare(strict_types=1);

namespace App\Domain\Shared\Support;

use App\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;

// Converts a Money amount into Indian-format words for statutory invoice
// fields ("Rupees ... and Paise ... Only"). Grouping uses the Indian system
// (crore, lakh, thousand) and paise are spelled out separately. Magnitudes
// above crore fold into the crore count, e.g. 1000 crore.
final class AmountInWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function inr(Money $amount): string
    {
        $value = (string) $amount;
        if (str_starts_with($value, '-')) {
            throw new InvalidArgumentException('Negative amounts have no invoice wording.');
        }
        [$rupees, $paise] = array_pad(explode('.', $value, 2), 2, '00');
        $rupees = ltrim($rupees, '0');
        $paise = str_pad(substr($paise, 0, 2), 2, '0');

        if ($rupees === '' && $paise === '00') {
            return 'Rupees Zero Only';
        }
        $words = 'Rupees '.($rupees !== '' ? self::indianWords($rupees) : 'Zero');
        if ($paise !== '00') {
            $words .= ' and Paise '.self::indianWords($paise);
        }

        return $words.' Only';
    }

    private static function indianWords(string $digits): string
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 'Zero';
        }
        $len = strlen($digits);
        // Indian pairs are taken from the right: last three digits, then two
        // digits each for thousand, lakh; everything above seven digits is a
        // count of crore, itself spelled with the same rules.
        $pair = fn (int $fromRight): string => substr($digits, max(0, $len - $fromRight), min(2, $len - $fromRight + 2));
        $parts = [];
        if ($len > 7) {
            $parts[] = self::indianWords(ltrim(substr($digits, 0, $len - 7), '0')).' Crore';
        }
        if ($len > 5) {
            $lakh = (int) $pair(7);
            if ($lakh > 0) {
                $parts[] = self::belowHundred($lakh).' Lakh';
            }
        }
        if ($len > 3) {
            $thousand = (int) $pair(5);
            if ($thousand > 0) {
                $parts[] = self::belowHundred($thousand).' Thousand';
            }
        }
        $tail = (int) substr($digits, -3);
        if ($tail > 0) {
            $parts[] = self::belowThousand($tail);
        }

        return implode(' ', $parts) ?: 'Zero';
    }

    private static function belowHundred(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        return trim(self::TENS[intdiv($n, 10)].($n % 10 !== 0 ? ' '.self::ONES[$n % 10] : ''));
    }

    private static function belowThousand(int $n): string
    {
        $hundreds = intdiv($n, 100);
        $remainder = $n % 100;
        $words = $hundreds > 0 ? self::ONES[$hundreds].' Hundred' : '';
        if ($remainder > 0) {
            $words .= ($words !== '' ? ' ' : '').self::belowHundred($remainder);
        }

        return $words;
    }
}
