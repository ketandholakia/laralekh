<?php
declare(strict_types=1);
namespace App\Domain\Shared\Support;

use App\Domain\Shared\ValueObjects\Money;
use InvalidArgumentException;

// Converts a Money amount into Indian-format words for statutory invoice
// fields ("Rupees ... and Paise ... Only"). Grouping uses the Indian system
// (crore, lakh, thousand) and paise are spelled out separately.
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
        $words = 'Rupees'.($rupees !== '' ? ' '.self::indianWords($rupees) : '');
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
        // Indian grouping: the last three digits stand alone, the leading
        // digits split into pairs from the right (thousand, lakh, crore, and
        // anything beyond folds into crore, e.g. 1000 crore).
        $tail = (int) substr($digits, -3);
        $leading = substr($digits, 0, strlen($digits) - 3);
        if ($leading !== '' && strlen($leading) % 2 === 1) {
            $leading = '0'.$leading;
        }
        $groups = [];
        for ($i = 0; $leading !== '' && $i < strlen($leading); $i += 2) {
            $groups[] = (int) substr($leading, $i, 2);
        }
        $parts = [];
        $count = count($groups);
        foreach ($groups as $i => $group) {
            if ($group === 0) {
                continue;
            }
            $fromRight = $count - 1 - $i;
            $name = $fromRight === 0 ? 'Thousand' : ($fromRight === 1 ? 'Lakh' : 'Crore');
            $parts[] = self::belowHundred($group).' '.$name;
        }
        if ($tail > 0) {
            $parts[] = self::belowThousand($tail);
        }
        return implode(' ', array_filter($parts)) ?: 'Zero';
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
