<main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <header class="flex flex-wrap items-end justify-between gap-4 border-b border-slate-200 pb-5">
        <div><p class="text-xs font-semibold uppercase tracking-[.18em] text-indigo-700">Compliance workspace · {{ $company->name }}</p><h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900">GST returns</h1><p class="mt-1 text-sm text-slate-600">Return periods from posted books, with snapshot and filing status.</p></div>
        <span class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600">Books → Validation → Draft → Final → Filed</span>
    </header>
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-900">Return periods</h2><span class="text-xs text-slate-500">{{ $periods->count() }} periods</span></div>
        @if($periods->isEmpty())<div class="px-6 py-14 text-center"><p class="font-medium text-slate-800">No posted activity yet</p><p class="mt-1 text-sm text-slate-500">Periods appear when invoices, corrections, or purchase bills are posted.</p></div>
        @else<div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Period</th><th class="px-5 py-3">GSTR-1</th><th class="px-5 py-3">GSTR-3B</th><th class="px-5 py-3">Filed</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
            @foreach($periods as $row)<tr class="hover:bg-slate-50/70"><th class="px-5 py-4 font-semibold text-slate-900">{{ \Carbon\CarbonImmutable::createFromFormat('!Y-m',$row['period'])->format('F Y') }}</th>
                @foreach(['gstr1','gstr3b'] as $type)<td class="px-5 py-4"><span class="inline-flex items-center gap-2"><span class="h-2 w-2 rounded-full {{ $row[$type]?->status==='final'?'bg-emerald-500':($row[$type]?'bg-amber-500':'bg-slate-300') }}"></span><span class="text-slate-700">{{ $row[$type]?->status ? ucfirst($row[$type]->status).' · v'.$row[$type]->version : 'Not generated' }}</span></span></td>@endforeach
                <td class="px-5 py-4 text-slate-600">{{ $row['filings']->count() ? 'Filed' : 'Not filed' }}</td><td class="px-5 py-4 text-right"><a class="font-semibold text-indigo-700 hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500" href="{{ route('gst.returns.show',$row['period']) }}">Review period <span aria-hidden="true">→</span></a></td></tr>@endforeach
        </tbody></table></div>@endif
    </section>
</main>
