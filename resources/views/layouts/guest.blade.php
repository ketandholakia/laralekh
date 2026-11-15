<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'LaraLekh') }}</title>
    <meta name="theme-color" content="#0F766E">
    <link rel="icon" href="{{ asset('brand/favicon.svg') }}" type="image/svg+xml">
    <link rel="alternate icon" href="{{ asset('brand/icons/favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('brand/icons/apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/inter-latin-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/inter-latin-600.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @livewireStyles
    @stack('styles')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-brand-700 focus:px-4 focus:py-2 focus:text-sm focus:text-white">Skip to content</a>
<main id="main" class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <a href="{{ route('login') }}" class="text-4xl" aria-label="LaraLekh — Smart Accounts. Simplified Business.">
        <x-brand-logo/>
    </a>
    <p class="mt-2 text-sm text-slate-500">Smart Accounts. Simplified Business.</p>
    <div class="mt-8 w-full max-w-md">
        {{ $slot ?? '' }}
        @yield('content')
    </div>
    <p class="mt-8 text-xs text-slate-400">{{ config('app.name', 'LaraLekh') }} · GST accounting for Indian business</p>
</main>
@livewireScripts
@stack('scripts')
</body>
</html>
