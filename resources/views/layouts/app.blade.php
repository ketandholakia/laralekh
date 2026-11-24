{{-- LaraLekh application shell: navy sidebar (collapsible on desktop, drawer on
     mobile) plus a sticky header with the current company and user menu. This
     layout renders inside the tenant scope: every Livewire full-page component
     is registered behind the company.context middleware, so the permission
     checks below always run with an active tenant context. --}}
@php
    use App\Domain\Masters\Models\Company;
    use App\Domain\Shared\Tenancy\CompanySession;

    $companySession = app(CompanySession::class);
    $sessionCompany = $companySession->companyId() !== null ? Company::find($companySession->companyId()) : null;
    $user = auth()->user();
    $rail = request()->cookie('laralekh_rail') === '1';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'LaraLekh'))</title>
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
<body x-data="{
    nav: false,
    rail: {{ $rail ? 'true' : 'false' }},
    tip: null,
    toggleRail() {
        this.rail = !this.rail;
        this.tip = null;
        document.cookie = 'laralekh_rail=' + (this.rail ? '1' : '0') + '; path=/; max-age=31536000; samesite=strict';
    },
    showTip(e) {
        if (!this.rail) { this.tip = null; return; }
        const el = e.target.closest('[data-tip]');
        if (!el) { this.tip = null; return; }
        const r = el.getBoundingClientRect();
        this.tip = { label: el.getAttribute('data-tip'), top: Math.round(r.top + r.height / 2) };
    },
    hideTip() { this.tip = null; },
}" class="min-h-screen bg-slate-50 text-slate-900 antialiased">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[60] focus:rounded-md focus:bg-brand-700 focus:px-4 focus:py-2 focus:text-sm focus:text-white">Skip to content</a>

{{-- Mobile drawer backdrop --}}
<div class="fixed inset-0 z-40 bg-navy/50 backdrop-blur-sm lg:hidden" x-show="nav" x-cloak x-transition.opacity @click="nav = false" aria-hidden="true"></div>

{{-- Sidebar. Labels and headings are removed from layout when collapsed
     (lg:hidden) — never overflow-clipped; overflow-hidden only smooths the
     width transition. The rail tooltip lives at body level (fixed) so the
     sidebar's own overflow never cuts it. --}}
<aside class="fixed inset-y-0 left-0 z-50 flex flex-col overflow-hidden bg-navy text-white transition-transform duration-200 lg:transition-all lg:duration-200"
       :class="(nav ? 'translate-x-0' : '-translate-x-full lg:translate-x-0') + ' ' + (rail ? 'lg:w-[68px]' : 'lg:w-60')"
       aria-label="Primary">
    <div class="flex h-16 shrink-0 items-center px-3" :class="rail ? 'lg:justify-center lg:px-0' : 'justify-between'">
        <a href="{{ route('dashboard') }}" class="text-3xl lg:text-[1.7rem]" aria-label="LaraLekh — Smart Accounts. Simplified Business."
           :class="rail ? 'lg:hidden' : ''">
            <x-brand-logo dark/>
        </a>
        <button type="button" class="hidden rounded-md p-1.5 text-slate-400 hover:bg-white/10 hover:text-white"
                :class="rail ? 'lg:hidden' : 'lg:block'"
                @click="toggleRail()" :aria-expanded="(! rail) ? 'true' : 'false'" aria-label="Collapse sidebar">
            <x-icon name="menu" class="h-5 w-5"/>
        </button>
        <button type="button" class="hidden rounded-md p-2 text-slate-300 hover:bg-white/10 hover:text-white"
                :class="rail ? 'lg:block' : 'lg:hidden'"
                @click="toggleRail()" aria-label="Expand sidebar" title="Expand sidebar">
            <span class="block text-[1.7rem] leading-none"><x-brand-logo dark mark-only/></span>
        </button>
        <button type="button" class="rounded-md p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" @click="nav = false" aria-label="Close navigation">
            <x-icon name="close" class="h-5 w-5"/>
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto overflow-x-hidden px-3 py-4" aria-label="Main"
         @mouseover="showTip($event)" @focusin="showTip($event)" @mouseout="hideTip($event)" @focusout="hideTip($event)">
        <div class="space-y-1">
            <p class="whitespace-nowrap px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" :class="rail ? 'lg:hidden' : ''">Overview</p>
            <x-nav-item :href="route('dashboard')" label="Dashboard" icon="dashboard" :active="request()->routeIs('dashboard')"/>
        </div>

        @can('invoices.view')
        <div class="space-y-1">
            <p class="whitespace-nowrap px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" :class="rail ? 'lg:hidden' : ''">Sales</p>
            <x-nav-item :href="route('invoices.index')" label="Invoices" icon="invoice" :active="request()->routeIs('invoices.*')"/>
        </div>
        @endcan

        @can('receipts.view')
        <div class="space-y-1">
            <p class="whitespace-nowrap px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" :class="rail ? 'lg:hidden' : ''">Money in</p>
            <x-nav-item :href="route('receipts.index')" label="Receipts" icon="receipt" :active="request()->routeIs('receipts.*')"/>
            <x-nav-item :href="route('customers.index')" label="Customers" icon="users" :active="request()->routeIs('customers.*')"/>
        </div>
        @endcan

        @can('purchases.view')
        <div class="space-y-1">
            <p class="whitespace-nowrap px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" :class="rail ? 'lg:hidden' : ''">Purchases</p>
            <x-nav-item :href="route('purchases.index')" label="Purchase bills" icon="cart" :active="request()->routeIs('purchases.*')"/>
        </div>
        @endcan

        @if (app(\App\Domain\Shared\Tenancy\TenantContext::class)->hasContext() && (auth()->user()?->can('items.view') || auth()->user()?->can('items.manage')))
        <div class="space-y-1">
            <p class="whitespace-nowrap px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" :class="rail ? 'lg:hidden' : ''">Inventory</p>
            <x-nav-item :href="route('items.index')" label="Items" icon="package" :active="request()->routeIs('items.*')"/>
        </div>
        @endif

        @can('returns.view')
        <div class="space-y-1">
            <p class="whitespace-nowrap px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" :class="rail ? 'lg:hidden' : ''">Compliance</p>
            <x-nav-item :href="route('gst.returns.index')" label="GST returns" icon="returns" :active="request()->routeIs('gst.returns.*')"/>
        </div>
        @endcan

        @if (app(\App\Domain\Shared\Tenancy\TenantContext::class)->hasContext() && (auth()->user()?->can('users.view') || auth()->user()?->can('users.manage') || auth()->user()?->can('roles.manage')))
        <div class="space-y-1">
            <p class="whitespace-nowrap px-3 pb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-400" :class="rail ? 'lg:hidden' : ''">Administration</p>
            @if (auth()->user()?->can('users.view') || auth()->user()?->can('users.manage'))
                <x-nav-item :href="route('admin.users.index')" label="Users" icon="users" :active="request()->routeIs('admin.users.*')"/>
            @endif
            @can('roles.manage')
                <x-nav-item :href="route('admin.roles.index')" label="Roles & Permissions" icon="user-circle" :active="request()->routeIs('admin.roles.*')"/>
            @endcan
            @if (auth()->user()?->can('users.manage') || auth()->user()?->hasRole('admin'))
                <x-nav-item :href="route('admin.pdf-customizer.index')" label="PDF Templates" icon="file-text" :active="request()->routeIs('admin.pdf-customizer.*')"/>
            @endif
        </div>
        @endif
    </nav>

    <div class="shrink-0 border-t border-white/10 p-3">
        <x-nav-item :href="route('company.select')" label="Switch company" icon="swap"/>
    </div>
</aside>

{{-- Content column --}}
<div class="flex min-h-screen flex-col" :class="rail ? 'lg:pl-[68px]' : 'lg:pl-60'">
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 shadow-card backdrop-blur">
        <div class="flex h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden" @click="nav = true" aria-label="Open navigation" :aria-expanded="nav ? 'true' : 'false'">
                    <x-icon name="menu" class="h-5 w-5"/>
                </button>
                @if ($sessionCompany)
                    <a href="{{ route('company.select') }}" class="group flex min-w-0 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm shadow-card hover:border-brand-600"
                       title="Switch company">
                        <x-icon name="building" class="h-4 w-4 shrink-0 text-brand-700"/>
                        <span class="max-w-[12rem] truncate font-medium text-slate-800">{{ $sessionCompany->name }}</span>
                        <x-icon name="chevron-down" class="h-3.5 w-3.5 shrink-0 text-slate-400"/>
                    </a>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                    <button type="button" class="flex items-center gap-2 rounded-md px-2 py-1.5 hover:bg-slate-100" @click="open = !open"
                            :aria-expanded="open ? 'true' : 'false'" aria-haspopup="menu" aria-label="Account menu">
                        <x-icon name="user-circle" class="h-8 w-8 text-slate-400"/>
                        <span class="hidden max-w-[10rem] truncate text-sm font-medium text-slate-700 sm:block">{{ $user?->name }}</span>
                        <x-icon name="chevron-down" class="h-3.5 w-3.5 text-slate-400"/>
                    </button>
                    <div class="absolute right-0 z-40 mt-2 w-56 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-pop"
                         x-show="open" x-cloak x-transition.origin.top.right role="menu">
                        <div class="border-b border-slate-100 px-4 py-3">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $user?->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user?->email }}</p>
                        </div>
                        @if (app(\App\Domain\Shared\Tenancy\TenantContext::class)->hasContext())
                            @if (auth()->user()?->can('users.view') || auth()->user()?->can('users.manage'))
                                <a href="{{ route('admin.users.index') }}" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                                    <x-icon name="users" class="h-4 w-4 text-slate-400"/> User Management
                                </a>
                            @endif
                            @can('roles.manage')
                                <a href="{{ route('admin.roles.index') }}" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50" role="menuitem">
                                    <x-icon name="user-circle" class="h-4 w-4 text-slate-400"/> Roles & Permissions
                                </a>
                            @endcan
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 hover:text-red-600" role="menuitem">
                                <x-icon name="logout" class="h-4 w-4"/> Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main id="main" class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <x-alert type="success" class="mb-4">{{ session('status') }}</x-alert>
        @endif
        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 py-4 text-center text-xs text-slate-400">
        LaraLekh · Smart Accounts. Simplified Business.
    </footer>
</div>

{{-- Rail tooltip: rendered at body level (fixed) so sidebar overflow never
     clips it; positioned beside the collapsed rail, vertically centered on
     the hovered or keyboard-focused item. Desktop rail only. --}}
<div x-show="tip !== null" x-cloak role="tooltip"
     class="pointer-events-none fixed left-[76px] z-[80] hidden -translate-y-1/2 whitespace-nowrap rounded-md bg-navy px-2.5 py-1.5 text-xs font-medium text-white shadow-pop lg:block"
     :style="tip ? 'top:' + tip.top + 'px' : ''"
     x-text="tip ? tip.label : ''"></div>
@livewireScripts
@stack('scripts')
</body>
</html>
