@props(['dark' => false, 'markOnly' => false])

{{-- LaraLekh brand lockup. Inline SVG keeps the mark crisp at any size and
     avoids an extra request; the wordmark is real text so it renders in the
     app's own Inter face. Use `dark` on navy/teal backgrounds. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="shrink-0" style="font-size:2rem;line-height:1" aria-hidden="true">
        @if ($dark)
            <svg viewBox="0 0 48 48" class="h-full w-full" style="width:1em;height:1em" fill="none" role="img" aria-label="LaraLekh">
                <rect x="2" y="2" width="44" height="44" rx="12" fill="#FFFFFF"/>
                <rect x="12" y="14" width="21" height="4.5" rx="2.25" fill="#115E59"/>
                <rect x="12" y="21.75" width="19" height="4.5" rx="2.25" fill="#115E59"/>
                <rect x="34" y="21.75" width="5" height="4.5" rx="2.25" fill="#F59E0B"/>
                <rect x="12" y="29.5" width="14" height="4.5" rx="2.25" fill="#115E59"/>
            </svg>
        @else
            <svg viewBox="0 0 48 48" class="h-full w-full" style="width:1em;height:1em" fill="none" role="img" aria-label="LaraLekh">
                <rect x="2" y="2" width="44" height="44" rx="12" fill="#0F766E"/>
                <rect x="12" y="14" width="21" height="4.5" rx="2.25" fill="#FFFFFF"/>
                <rect x="12" y="21.75" width="19" height="4.5" rx="2.25" fill="#FFFFFF"/>
                <rect x="34" y="21.75" width="5" height="4.5" rx="2.25" fill="#F59E0B"/>
                <rect x="12" y="29.5" width="14" height="4.5" rx="2.25" fill="#FFFFFF"/>
            </svg>
        @endif
    </span>
    @unless ($markOnly)
        <span class="whitespace-nowrap font-semibold tracking-tight {{ $dark ? 'text-white' : 'text-navy' }}">
            Lara<span class="{{ $dark ? 'text-teal-300' : 'text-brand-700' }} font-bold">Lekh</span>
        </span>
    @endunless
</span>
