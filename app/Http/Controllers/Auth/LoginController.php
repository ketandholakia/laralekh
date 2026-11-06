<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Shared\Tenancy\CompanySession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }
        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('company.select'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        app(CompanySession::class)->forget();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
