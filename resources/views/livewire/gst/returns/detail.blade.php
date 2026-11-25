<main class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
    <header class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5"><div><a href="{{ route('gst.returns.index') }}" class="text-sm font-medium text-indigo-700 hover:underline">← All return periods</a><p class="mt-4 text-xs font-semibold uppercase tracking-[.18em] text-indigo-700">GST return review</p><h1 class="mt-1 text-3xl font-semibold tracking-tight text-slate-900">{{ \Carbon\CarbonImmutable::createFromFormat('!Y-m',$period)->format('F Y') }}</h1></div><div class="rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-medium text-slate-700">Books <span class="text-indigo-600">→</span> Validation <span class="text-indigo-600">→</span> Draft <span class="text-indigo-600">→</span> Final <span class="text-indigo-600">→</span> Filed</div></header>
    @if($message)<div role="status" class="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">{{ $message }}</div>@endif
    @foreach($backdating as $warning)<div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ is_array($warning)?($warning['message']??json_encode($warning)):$warning }}</div>@endforeach
     <section class="grid gap-4 md:grid-cols-3"><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Validation</p><p class="mt-2 text-2xl font-semibold {{ $report['valid']?'text-emerald-700':'text-red-700' }}">{{ $report['valid']?'Ready':'Needs attention' }}</p><p class="mt-1 text-sm text-slate-600">{{ count($report['errors']??[]) }} blocking · {{ count($report['warnings']??[]) }} warnings</p></article><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">GSTR-1 documents</p><p class="mt-2 text-2xl font-semibold text-slate-900">{{ count($dataset['b2b']??[])+count($dataset['b2cl']??[])+count($dataset['b2cs']??[])+count($dataset['sez']??[])+count($dataset['advances']['received']??[]) }}</p><p class="mt-1 text-sm text-slate-600">Outward supply records</p></article><article class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">GSTR-3B summary</p>@if($summaryError)<p class="mt-2 text-sm text-amber-800">{{ $summaryError }}</p>@elseif($summary)<pre class="mt-2 overflow-auto text-xs text-slate-700">{{ json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>@else<p class="mt-2 text-sm text-slate-500">Resolve validation errors to build the reconciliation.</p>@endif</article></section>
    @if(count($dataset['advances']['table_11a'] ?? []))
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-semibold text-slate-900">Advances received (Table 11A)</h2>
            <p class="mt-1 text-sm text-slate-600">Statutory tax liability on account of advance receipts for services / undetermined supplies (CGST s. 31(3)(d), Rule 50).</p>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-4">Place of supply</th>
                            <th class="py-2 pr-4">Tax rate</th>
                            <th class="py-2 pr-4 text-right">Gross advance</th>
                            <th class="py-2 pr-4 text-right">Taxable value</th>
                            <th class="py-2 pr-4 text-right">CGST</th>
                            <th class="py-2 pr-4 text-right">SGST/UTGST</th>
                            <th class="py-2 text-right">IGST</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($dataset['advances']['table_11a'] as $adv)
                            <tr>
                                <td class="py-2 pr-4 font-mono">{{ $adv['place_of_supply'] }}</td>
                                <td class="py-2 pr-4">{{ $adv['rate'] }}%</td>
                                <td class="py-2 pr-4 text-right font-mono">₹{{ number_format((float) $adv['gross_amount'], 2) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">₹{{ number_format((float) $adv['taxable_amount'], 2) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">₹{{ number_format((float) $adv['cgst'], 2) }}</td>
                                <td class="py-2 pr-4 text-right font-mono">₹{{ number_format((float) bcadd($adv['sgst'], $adv['utgst'], 2), 2) }}</td>
                                <td class="py-2 text-right font-mono">₹{{ number_format((float) $adv['igst'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-slate-900">GSTR-2B inward reconciliation</h2>
                    @if($gstr2bImport)
                        <span class="rounded bg-indigo-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-wider text-indigo-700">
                            {{ strtoupper($gstr2bImport->file_format) }} · {{ $gstr2bImport->total_documents }} portal docs
                        </span>
                    @else
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                            No import
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-600">
                    Match official GSTR-2B inward documents against booked purchase bills by Supplier GSTIN and Document Number (CGST s. 16(2)(aa)).
                </p>
            </div>
            @if($gstr2bImport)
                @can('returns.finalize')
                    <div>
                        <button type="button" wire:click="deleteGstr2b" class="rounded border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
                            Remove / Re-import
                        </button>
                    </div>
                @endcan
            @endif
        </div>

        @if(! $gstr2bImport)
            <div class="mt-5 rounded-md border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                <p class="text-sm font-medium text-slate-900">Import GSTR-2B statement</p>
                <p class="mt-1 text-xs text-slate-500">Upload official GST Portal GSTR-2B JSON (docdata.b2b) or B2B CSV export file (max 10MB).</p>
                @can('returns.finalize')
                    <form wire:submit="importGstr2b" class="mt-4 flex flex-wrap items-center justify-center gap-3">
                        <input type="file" wire:model="gstr2bFile" accept=".json,.csv,application/json,text/csv" class="rounded border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:px-2.5 file:py-1 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                        <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center rounded-md bg-indigo-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50">
                            <span wire:loading wire:target="importGstr2b" class="mr-1.5">Processing...</span>
                            <span>Import &amp; Reconcile</span>
                        </button>
                    </form>
                    @error('gstr2bFile') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                @endcan
            </div>
        @else
            @php
                $summaryComp = $gstr2bImport->summary['comparison'] ?? [];
                $matched = $summaryComp['matched'] ?? ['count' => 0, 'tax' => '0.00'];
                $mismatch = $summaryComp['tax_mismatch'] ?? ['count' => 0, 'difference' => '0.00'];
                $missingPortal = $summaryComp['missing_in_portal'] ?? ['count' => 0, 'tax' => '0.00'];
                $missingBooks = $summaryComp['missing_in_books'] ?? ['count' => 0, 'tax' => '0.00'];
                $portalMeta = $gstr2bImport->summary['portal_2b'] ?? [];

                $allRecords = collect($gstr2bImport->records ?? []);
                $filteredRecords = $allRecords->filter(function ($item) use ($reconciliationFilter) {
                    if ($reconciliationFilter === 'all') return true;
                    if ($reconciliationFilter === 'matched') return ($item['status'] ?? '') === 'matched';
                    if ($reconciliationFilter === 'tax_mismatch') return ($item['status'] ?? '') === 'tax_mismatch';
                    if ($reconciliationFilter === 'missing_in_portal') return ($item['status'] ?? '') === 'missing_in_portal';
                    if ($reconciliationFilter === 'missing_in_books') return ($item['status'] ?? '') === 'missing_in_books';
                    if ($reconciliationFilter === 'ineligible') return !($item['itc_available'] ?? true);
                    return true;
                });
            @endphp

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-md border border-emerald-200 bg-emerald-50/50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Matched (±₹1)</p>
                    <p class="mt-1 text-2xl font-semibold text-emerald-900">{{ $matched['count'] }}</p>
                    <p class="mt-0.5 font-mono text-xs text-emerald-700">₹{{ number_format((float) ($matched['tax'] ?? 0), 2) }} ITC</p>
                    <p class="mt-1 text-[11px] text-emerald-600">Books &amp; portal values match</p>
                </article>

                <article class="rounded-md border border-amber-200 bg-amber-50/50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Tax mismatch</p>
                    <p class="mt-1 text-2xl font-semibold text-amber-900">{{ $mismatch['count'] }}</p>
                    <p class="mt-0.5 font-mono text-xs text-amber-700">Diff: ₹{{ number_format((float) ($mismatch['difference'] ?? 0), 2) }}</p>
                    <p class="mt-1 text-[11px] text-amber-600">Portal vs Books tax &gt; ₹1.00</p>
                </article>

                <article class="rounded-md border border-red-200 bg-red-50/50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-red-800">Missing in portal</p>
                    <p class="mt-1 text-2xl font-semibold text-red-900">{{ $missingPortal['count'] }}</p>
                    <p class="mt-0.5 font-mono text-xs text-red-700">₹{{ number_format((float) ($missingPortal['tax'] ?? 0), 2) }} tax</p>
                    <p class="mt-1 text-[11px] text-red-600">Section 16(2)(aa) supplier risk</p>
                </article>

                <article class="rounded-md border border-blue-200 bg-blue-50/50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-800">Missing in books</p>
                    <p class="mt-1 text-2xl font-semibold text-blue-900">{{ $missingBooks['count'] }}</p>
                    <p class="mt-0.5 font-mono text-xs text-blue-700">₹{{ number_format((float) ($missingBooks['tax'] ?? 0), 2) }} ITC</p>
                    <p class="mt-1 text-[11px] text-blue-600">Unclaimed ITC on portal</p>
                </article>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600 border-t border-slate-100 pt-3">
                <div>
                    <span>Source: <strong class="text-slate-800">{{ $gstr2bImport->source_filename }}</strong></span>
                    <span class="mx-1.5">·</span>
                    <span>Imported {{ $gstr2bImport->imported_at?->format('d M Y, h:i A') }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <span>Portal ITC eligible: <strong class="font-mono text-emerald-800">₹{{ number_format((float) ($portalMeta['itc_available_tax'] ?? 0), 2) }}</strong></span>
                    <span>Statutory ineligible: <strong class="font-mono text-red-800">₹{{ number_format((float) ($portalMeta['itc_ineligible_tax'] ?? 0), 2) }}</strong></span>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-b border-slate-200 pb-2">
                <button type="button" wire:click="$set('reconciliationFilter', 'all')" class="rounded-full px-3 py-1 text-xs font-medium {{ $reconciliationFilter === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    All ({{ $allRecords->count() }})
                </button>
                <button type="button" wire:click="$set('reconciliationFilter', 'matched')" class="rounded-full px-3 py-1 text-xs font-medium {{ $reconciliationFilter === 'matched' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}">
                    Matched ({{ $matched['count'] }})
                </button>
                <button type="button" wire:click="$set('reconciliationFilter', 'tax_mismatch')" class="rounded-full px-3 py-1 text-xs font-medium {{ $reconciliationFilter === 'tax_mismatch' ? 'bg-amber-700 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' }}">
                    Tax mismatch ({{ $mismatch['count'] }})
                </button>
                <button type="button" wire:click="$set('reconciliationFilter', 'missing_in_portal')" class="rounded-full px-3 py-1 text-xs font-medium {{ $reconciliationFilter === 'missing_in_portal' ? 'bg-red-700 text-white' : 'bg-red-50 text-red-800 hover:bg-red-100' }}">
                    Missing in portal ({{ $missingPortal['count'] }})
                </button>
                <button type="button" wire:click="$set('reconciliationFilter', 'missing_in_books')" class="rounded-full px-3 py-1 text-xs font-medium {{ $reconciliationFilter === 'missing_in_books' ? 'bg-blue-700 text-white' : 'bg-blue-50 text-blue-800 hover:bg-blue-100' }}">
                    Missing in books ({{ $missingBooks['count'] }})
                </button>
            </div>

            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead>
                        <tr class="text-left font-semibold uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2 pr-3">Supplier GSTIN &amp; name</th>
                            <th class="py-2 pr-3">Document no &amp; date</th>
                            <th class="py-2 pr-3 text-right">Books tax</th>
                            <th class="py-2 pr-3 text-right">Portal tax</th>
                            <th class="py-2 pr-3 text-right">Difference</th>
                            <th class="py-2">ITC status / Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($filteredRecords as $rec)
                            <tr>
                                <td class="py-2.5 pr-3">
                                    @if(($rec['status'] ?? '') === 'matched')
                                        <span class="inline-flex rounded bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-800">MATCHED</span>
                                    @elseif(($rec['status'] ?? '') === 'tax_mismatch')
                                        <span class="inline-flex rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-800">TAX MISMATCH</span>
                                    @elseif(($rec['status'] ?? '') === 'missing_in_portal')
                                        <span class="inline-flex rounded bg-red-100 px-2 py-0.5 font-semibold text-red-800">MISSING IN PORTAL</span>
                                    @elseif(($rec['status'] ?? '') === 'missing_in_books')
                                        <span class="inline-flex rounded bg-blue-100 px-2 py-0.5 font-semibold text-blue-800">MISSING IN BOOKS</span>
                                    @else
                                        <span class="inline-flex rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">{{ strtoupper($rec['status'] ?? 'OTHER') }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3">
                                    <div class="font-mono text-slate-900">{{ $rec['supplier_gstin'] ?? '— (Unregistered)' }}</div>
                                    <div class="text-[11px] text-slate-500 truncate max-w-[200px]">{{ $rec['supplier_name'] ?: '—' }}</div>
                                </td>
                                <td class="py-2.5 pr-3">
                                    <div class="font-mono font-medium text-slate-900">{{ $rec['document_number'] }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $rec['date'] }}</div>
                                </td>
                                <td class="py-2.5 pr-3 text-right font-mono">
                                    ₹{{ number_format((float) ($rec['books_tax'] ?? 0), 2) }}
                                </td>
                                <td class="py-2.5 pr-3 text-right font-mono">
                                    ₹{{ number_format((float) ($rec['portal_tax'] ?? 0), 2) }}
                                </td>
                                <td class="py-2.5 pr-3 text-right font-mono {{ bccomp((string) ($rec['difference'] ?? 0), '0.00', 2) !== 0 ? 'text-amber-700 font-semibold' : 'text-slate-600' }}">
                                    ₹{{ number_format((float) ($rec['difference'] ?? 0), 2) }}
                                </td>
                                <td class="py-2.5 text-[11px]">
                                    @if(! ($rec['itc_available'] ?? true))
                                        <span class="font-semibold text-red-700">Ineligible:</span>
                                        <span class="text-red-600">{{ $rec['ineligibility_reason'] ?? 'ITC not available' }}</span>
                                    @else
                                        <span class="text-emerald-700 font-medium">Eligible ITC</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-6 text-center text-slate-500">
                                    No records match the selected filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>
    @if(count($report['errors']??[]))<section class="rounded-lg border border-red-200 bg-red-50 p-5"><h2 class="font-semibold text-red-900">Blocking errors</h2><ul class="mt-3 list-inside list-disc space-y-1 text-sm text-red-800">@foreach($report['errors'] as $error)<li>{{ $error['message']??$error }}</li>@endforeach</ul></section>@endif
    @if(count($report['warnings']??[]))<section class="rounded-lg border border-amber-200 bg-amber-50 p-5"><h2 class="font-semibold text-amber-900">CA review warnings</h2><ul class="mt-3 list-inside list-disc space-y-1 text-sm text-amber-800">@foreach($report['warnings'] as $warning)<li>{{ $warning['message']??$warning }}</li>@endforeach</ul></section>@endif
     <section class="rounded-lg border border-slate-200 bg-white p-5"><div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="font-semibold text-slate-900">CSV review exports</h2><p class="mt-1 text-sm text-slate-600">Section files use official Offline Tool 3.2.4 headers. “Review summary” is LaraLekh-only and is not a GST import template.</p><label class="mt-3 block text-xs font-medium text-slate-600">Preceding-FY aggregate turnover (whole rupees; required for HSN digit checks)<input type="number" min="0" step="1" wire:model.live="annualTurnoverRupees" class="mt-1 block w-72 rounded border-slate-300 text-sm"></label></div>@can('returns.export')<div class="flex flex-wrap gap-2">@foreach(['review-summary.csv','b2b,sez,de.csv','b2cl.csv','b2cs.csv','exp.csv','cdnr.csv','cdnur.csv','hsn(b2b).csv','hsn(b2c).csv','docs.csv'] as $file)<a class="rounded border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:border-indigo-400 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500" href="{{ route('gst.returns.csv',['period'=>$period,'filename'=>$file,'annual_turnover_rupees'=>$annualTurnoverRupees]) }}">{{ $file }}</a>@endforeach</div>@endcan</div></section>
    <section class="rounded-lg border border-slate-200 bg-white p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold text-slate-900">Snapshot history</h2><p class="mt-1 text-sm text-slate-600">Versioned records, content hashes, and filing status.</p></div>@can('returns.finalize')<div class="flex gap-2"><button wire:click="generate('gstr1')" class="rounded-md bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500">Generate GSTR-1 draft</button><button wire:click="generate('gstr3b')" class="rounded-md border border-indigo-700 px-4 py-2 text-sm font-semibold text-indigo-800 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500">Generate GSTR-3B draft</button></div>@endcan</div>
        <div class="mt-5 divide-y divide-slate-100">@forelse($snapshots as $snapshot)<div class="flex flex-wrap items-center justify-between gap-4 py-4"><div><p class="font-medium text-slate-900">{{ strtoupper($snapshot->type) }} · v{{ $snapshot->version }} <span class="ml-2 rounded px-2 py-1 text-xs {{ $snapshot->status==='final'?'bg-emerald-100 text-emerald-800':($snapshot->status==='draft'?'bg-amber-100 text-amber-900':'bg-slate-100 text-slate-600') }}">{{ ucfirst($snapshot->status) }}</span></p><p class="mt-1 break-all font-mono text-xs text-slate-500">SHA-256 {{ $snapshot->sha256 }}</p></div><div class="flex flex-wrap items-center gap-3">@if($snapshot->status==='draft' && auth()->user()->can('returns.finalize'))<button wire:click="finalize({{ $snapshot->id }})" @disabled(!($snapshot->payload['validation']['valid']??true)) class="rounded border border-emerald-700 px-3 py-2 text-sm font-medium text-emerald-800 disabled:cursor-not-allowed disabled:opacity-40">Finalize</button>@elseif($snapshot->status==='final' && auth()->user()->can('returns.finalize'))<form wire:submit="markFiled({{ $snapshot->id }})" class="flex flex-wrap gap-2"><input wire:model="arn" aria-label="ARN" placeholder="ARN" required class="w-36 rounded border-slate-300 text-sm"><input wire:model="filedOn" aria-label="Filed date" type="date" required class="rounded border-slate-300 text-sm"><button class="rounded bg-emerald-700 px-3 py-2 text-sm font-semibold text-white">Record filed</button></form>@endif</div></div>@empty<p class="py-6 text-sm text-slate-500">No snapshots have been generated for this period.</p>@endforelse
        @foreach($filings as $filing)<p class="mt-2 text-sm text-emerald-800">{{ strtoupper($filing->type) }} filed {{ $filing->filed_on->format('j M Y') }} · ARN {{ $filing->arn }}</p>@endforeach</div>
    </section>
</main>
