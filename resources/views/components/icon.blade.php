@php
    // Lucide-style stroke geometry (24×24 grid, ISC-licensed path data),
    // inlined so the app has no icon package dependency.
    $paths = [
        'dashboard' => ['M3 3h7v9H3z', 'M14 3h7v5h-7z', 'M14 12h7v9h-7z', 'M3 16h7v5H3z'],
        'invoice' => ['M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z', 'M14 2v4a2 2 0 0 0 2 2h4', 'M16 13H8', 'M16 17H8', 'M10 9H8'],
        'receipt' => ['M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z', 'M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8', 'M12 17.5v-11'],
        'users' => ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
        'cart' => ['M6 6h15l-1.5 9H8L6 3H3', 'M6 6l-.7-3H2', 'M9 20a1.5 1.5 0 1 0 0 .01', 'M18 20a1.5 1.5 0 1 0 0 .01'],
        'returns' => ['M12 22a10 10 0 1 0-10-10', 'M2 12V7h5', 'M12 7v5l3 3'],
        'building' => ['M4 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18', 'M16 8h3a2 2 0 0 1 2 2v12', 'M2 22h20', 'M8 6h2', 'M8 10h2', 'M8 14h2', 'M8 18h2', 'M12 6h.01', 'M12 10h.01', 'M12 14h.01', 'M12 18h.01'],
        'menu' => ['M4 6h16', 'M4 12h16', 'M4 18h16'],
        'close' => ['M18 6 6 18', 'M6 6l12 12'],
        'chevron-down' => ['M6 9l6 6 6-6'],
        'chevron-right' => ['M9 18l6-6-6-6'],
        'arrow-left' => ['M19 12H5', 'M12 19l-7-7 7-7'],
        'arrow-right' => ['M5 12h14', 'M12 5l7 7-7 7'],
        'search' => ['M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Z', 'M21 21l-4.35-4.35'],
        'plus' => ['M5 12h14', 'M12 5v14'],
        'eye' => ['M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z', 'M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z'],
        'eye-off' => ['M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 8 10 8a13.16 13.16 0 0 1-1.67 2.68', 'M6.61 6.61A13.5 13.5 0 0 0 2 12s3.5 8 10 8a9.74 9.74 0 0 0 5.39-1.61', 'M2 2l20 20', 'M9.88 9.88a3 3 0 1 0 4.24 4.24'],
        'logout' => ['M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4', 'M16 17l5-5-5-5', 'M21 12H9'],
        'check-circle' => ['M22 11.08V12a10 10 0 1 1-5.93-9.14', 'M22 4 12 14.01l-3-3'],
        'alert' => ['M12 9v4', 'M12 17h.01', 'M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z'],
        'info' => ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M12 16v-4', 'M12 8h.01'],
        'clock' => ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M12 6v6l4 2'],
        'wallet' => ['M21 12V7H5a2 2 0 0 1 0-4h14v4', 'M3 5v14a2 2 0 0 0 2 2h16v-5', 'M18 12a2 2 0 0 0 0 4h4v-4Z'],
        'file-down' => ['M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z', 'M14 2v4a2 2 0 0 0 2 2h4', 'M12 18v-6', 'M9 15l3 3 3-3'],
        'user-circle' => ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z', 'M12 12a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z', 'M5.5 19a8 8 0 0 1 13 0'],
        'swap' => ['M8 3 4 7l4 4', 'M4 7h16', 'M16 21l4-4-4-4', 'M20 17H4'],
    ];
    $name = $name ?? array_key_first($paths);
    $paths = $paths[$name] ?? throw new InvalidArgumentException("Unknown icon [{$name}].");
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
     {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0', 'aria-hidden' => 'true']) }}>
    @foreach ($paths as $d)
        <path d="{{ $d }}"/>
    @endforeach
</svg>
