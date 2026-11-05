@extends('layouts.app')

@section('content')
<div class="max-w-sm mx-auto mt-16 bg-white rounded-lg border border-slate-200 p-6">
    <h1 class="text-lg font-semibold mb-4">Log in</h1>
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" required autofocus
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Password</label>
            <input id="password" name="password" type="password" required
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="rounded border-slate-300"> Remember me
        </label>
        <button type="submit" class="w-full rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Log in</button>
    </form>
</div>
@endsection
