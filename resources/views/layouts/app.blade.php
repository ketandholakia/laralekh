<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravix') }}</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @livewireStyles
    @stack('styles')
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
<header class="bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
        <a href="{{ route('invoices.index') }}" class="font-semibold text-slate-900">Laravix</a>
        @auth
        <nav class="flex items-center gap-4 text-sm">
            <a href="{{ route('invoices.index') }}" class="hover:text-indigo-600">Invoices</a>
            <a href="{{ route('receipts.index') }}" class="hover:text-indigo-600">Receipts</a>
            <a href="{{ route('customers.index') }}" class="hover:text-indigo-600">Customers</a>
            <a href="{{ route('company.select') }}" class="hover:text-indigo-600">Company</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-slate-500 hover:text-red-600">Log out</button>
            </form>
        </nav>
        @endauth
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-6">
    @if (session('status'))
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800">{{ session('status') }}</div>
    @endif
    {{ $slot }}
</main>
@livewireScripts
@stack('scripts')
</body>
</html>
