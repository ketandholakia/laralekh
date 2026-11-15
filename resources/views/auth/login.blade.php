@extends('layouts.guest')

@section('content')
<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-card sm:p-8" x-data="{ show: false, busy: false }">
    <h1 class="text-lg font-semibold text-slate-900">Welcome back</h1>
    <p class="mt-1 text-sm text-slate-500">Sign in to your workspace.</p>

    <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4" @submit="busy = true">
        @csrf
        <x-field label="Email" name="email" :required="true" :error="$errors->first('email')">
            <x-input name="email" type="email" required autofocus autocomplete="email"
                     placeholder="you@company.in" value="{{ old('email') }}"/>
        </x-field>

        <x-field label="Password" name="password" :required="true" :error="$errors->first('password')">
            <div class="relative">
                <x-input name="password" required autocomplete="current-password" placeholder="••••••••" class="pr-10"
                         x-bind:type="show ? 'text' : 'password'"/>
                <button type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600"
                        @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'">
                    <x-icon name="eye-off" class="h-4 w-4" x-show="show" x-cloak/>
                    <x-icon name="eye" class="h-4 w-4" x-show="!show"/>
                </button>
            </div>
        </x-field>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600/20">
            Remember me
        </label>

        <x-button type="submit" variant="saffron" class="w-full" x-bind:disabled="busy" aria-label="Log in">
            <span x-show="!busy">Log in</span>
            <span x-show="busy" x-cloak class="inline-flex items-center gap-2">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-navy/30 border-t-navy" role="status" aria-label="Signing in"></span>
                Signing in…
            </span>
        </x-button>
    </form>
</div>
@endsection
