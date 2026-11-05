@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto mt-10 bg-white rounded-lg border border-slate-200 p-6">
    <h1 class="text-lg font-semibold mb-1">Select company</h1>
    <p class="text-sm text-slate-500 mb-4">Only companies you are a member of are listed. Choosing a company resets the financial-year selection.</p>
    <form method="POST" action="{{ route('company.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="company_id" class="block text-sm font-medium">Company</label>
            <select id="company_id" name="company_id" required
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
                <option value="">— choose —</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected($company->id === $selectedCompanyId)>{{ $company->name }}</option>
                @endforeach
            </select>
            @error('company_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        @if (count($years) > 0)
        <div>
            <label for="financial_year_id" class="block text-sm font-medium">Financial year (open)</label>
            <select id="financial_year_id" name="financial_year_id"
                    class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:outline-none">
                <option value="">— choose later —</option>
                @foreach ($years as $year)
                    <option value="{{ $year->id }}" @selected($year->id === $selectedYearId)>{{ $year->name }} ({{ $year->starts_on }} to {{ $year->ends_on }})</option>
                @endforeach
            </select>
            @error('financial_year_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        @endif
        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">Continue</button>
    </form>
</div>
@endsection
