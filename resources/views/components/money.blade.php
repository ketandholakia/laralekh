@props(['value', 'decimals' => 2, 'currency' => '₹'])

@php
    // Display-only Indian digit grouping (lakh/crore). Never used inside
    // posting or GST calculations — those keep their own exact arithmetic.
    // Accepts numeric strings, ints/floats, and the Money value object
    // (Stringable) that MoneyCast attaches to model attributes.
    $amount = (float) (string) $value;
    $negative = $amount < 0;
    $formatted = number_format(abs($amount), $decimals, '.', '');
    [$integer, $fraction] = explode('.', $formatted);
    if (strlen($integer) > 3) {
        $head = substr($integer, 0, -3);
        $tail = substr($integer, -3);
        $integer = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head).','.$tail;
    }
@endphp

<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ $negative ? '−' : '' }}{{ $currency }}{{ $integer }}.{{ $fraction }}</span>
