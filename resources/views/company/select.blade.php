@extends('layouts.guest')

@section('content')
<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-card sm:p-8">
    {{-- Signed-in identity --}}
    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
        <x-icon name="user-circle" class="h-10 w-10 text-slate-300"/>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
        </div>
    </div>

    <h1 class="mt-5 text-lg font-semibold text-slate-900">Choose a company</h1>
    <p class="mt-1 text-sm text-slate-500">Only companies you are a member of are listed. Choosing a company resets the financial-year selection.</p>

    @if (count($companies) > 0)
        <form method="POST" action="{{ route('company.store') }}" class="mt-5 space-y-5" x-data>
            @csrf
            <fieldset class="space-y-2.5">
                <legend class="sr-only">Company</legend>
                @foreach ($companies as $company)
                    <label class="block cursor-pointer">
                        <input type="radio" name="company_id" value="{{ $company->id }}" required
                               class="peer sr-only"
                               @checked($company->id === $selectedCompanyId)>
                        <span class="flex items-start gap-3 rounded-lg border border-slate-200 p-3.5 transition-colors hover:border-brand-600
                                     peer-checked:border-brand-700 peer-checked:bg-brand-50 peer-focus-visible:outline-2 peer-focus-visible:outline-brand-600">
                            <span class="mt-0.5 rounded-md bg-brand-50 p-1.5 text-brand-700 peer-checked:bg-white">
                                <x-icon name="building" class="h-4 w-4"/>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ $company->name }}</span>
                                @if ($company->gstin)
                                    <span class="mt-0.5 block font-mono text-xs text-slate-500">GSTIN {{ $company->gstin }}</span>
                                @endif
                                @if ($company->address)
                                    <span class="mt-0.5 block line-clamp-1 text-xs text-slate-400">{{ str_replace("\n", ', ', $company->address) }}</span>
                                @endif
                            </span>
                        </span>
                    </label>
                @endforeach
            </fieldset>
            @error('company_id')<p class="text-xs text-red-600" role="alert">{{ $message }}</p>@enderror

            @if (count($years) > 0)
                <x-field label="Financial year (open)" name="financial_year_id" :error="$errors->first('financial_year_id')">
                    <x-select name="financial_year_id">
                        <option value="">— choose later —</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" @selected($year->id === $selectedYearId)>
                                {{ $year->name }} ({{ $year->starts_on }} to {{ $year->ends_on }})
                            </option>
                        @endforeach
                    </x-select>
                </x-field>
            @endif

            <x-button variant="primary" class="w-full">Continue</x-button>
        </form>
    @else
        <x-empty-state icon="building" title="No companies yet"
                       message="You are not a member of any company yet. Ask an administrator to add you to your company."/>
    @endif
</div>
@endsection
