<div>
    <x-page-header :eyebrow="$company->name" title="Dashboard" :sub="'Accounting overview · '.$monthLabel">
        @if (auth()->user()->can('invoices.create'))
            <x-button :href="route('invoices.create')" variant="primary"><x-icon name="plus" class="h-4 w-4"/> New invoice</x-button>
        @endif
    </x-page-header>

    {{-- Every financial card is permission-gated in both directions: the
         aggregate is not even computed without the matching view permission,
         and the markup below never renders it. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @can('invoices.view')
            <x-kpi label="Sales · {{ $monthLabel }}" icon="invoice"><x-money :value="$salesMonth"/></x-kpi>
        @endcan
        @can('purchases.view')
            <x-kpi label="Purchases · {{ $monthLabel }}" icon="cart"><x-money :value="$purchasesMonth"/></x-kpi>
        @endcan
        @can('receipts.view')
            <x-kpi label="Receipts · {{ $monthLabel }}" icon="wallet"><x-money :value="$receiptsMonth"/></x-kpi>
        @endcan
        @can('receipts.view')
            <x-kpi label="Receivables (open)" icon="clock">
                <x-money :value="$receivables"/>
                <x-slot:sub>
                    @if ($excessCredits !== null && ! $excessCredits->isZero())
                        Excess customer credits <x-money :value="$excessCredits"/> reported separately, not counted as receivable
                    @else
                        Posted invoices less settlements, per the settlement ledger
                    @endif
                </x-slot:sub>
            </x-kpi>
        @endcan
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {{-- Recent posted invoices (invoice permission required) --}}
        @can('invoices.view')
        <section class="lg:col-span-2 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Recent invoices</h2>
                <a href="{{ route('invoices.index') }}" class="inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:text-brand-800">
                    All invoices <x-icon name="arrow-right" class="h-3.5 w-3.5"/>
                </a>
            </div>
            @if ($recentInvoices->isEmpty())
                <x-empty-state icon="invoice" title="No posted invoices yet"
                               message="Invoices appear here once they are posted from the invoice editor."/>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Number</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Party</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Date</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentInvoices as $invoice)
                                <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                                    <td class="px-5 py-3 font-mono text-xs">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="text-brand-700 hover:text-brand-800 hover:underline">
                                            {{ $invoice->series.'/'.$invoice->number }}
                                        </a>
                                    </td>
                                    <td class="max-w-[14rem] truncate px-5 py-3 text-slate-700">{{ $invoice->party?->name }}</td>
                                    <td class="whitespace-nowrap px-5 py-3 text-slate-500">{{ $invoice->date->format('d-m-Y') }}</td>
                                    <td class="px-5 py-3 text-right"><x-money :value="$invoice->total" class="font-medium"/></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
        @endcan

        {{-- Quick actions + GST compliance --}}
        <div class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
                <h2 class="text-sm font-semibold text-slate-900">Quick actions</h2>
                <div class="mt-3 grid grid-cols-1 gap-2">
                    @can('invoices.create')
                        <x-button :href="route('invoices.create')" variant="secondary" class="w-full justify-start">
                            <x-icon name="plus" class="h-4 w-4 text-brand-700"/> Create invoice
                        </x-button>
                    @endcan
                    @can('receipts.create')
                        <x-button :href="route('receipts.create')" variant="secondary" class="w-full justify-start">
                            <x-icon name="wallet" class="h-4 w-4 text-brand-700"/> Record receipt
                        </x-button>
                    @endcan
                    @can('purchases.create')
                        <x-button :href="route('purchases.create')" variant="secondary" class="w-full justify-start">
                            <x-icon name="cart" class="h-4 w-4 text-brand-700"/> Enter purchase bill
                        </x-button>
                    @endcan
                    @can('returns.view')
                        <x-button :href="route('gst.returns.index')" variant="secondary" class="w-full justify-start">
                            <x-icon name="returns" class="h-4 w-4 text-brand-700"/> GST compliance workspace
                        </x-button>
                    @endcan
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-card">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">GST compliance</h2>
                    @can('returns.view')
                        <a href="{{ route('gst.returns.index') }}" class="text-xs font-medium text-brand-700 hover:text-brand-800">Workspace</a>
                    @endcan
                </div>
                @can('returns.view')
                    @if ($latestFilings->isEmpty() && $latestDrafts->isEmpty())
                        <x-empty-state icon="returns" title="No GST activity yet"
                                       message="Filed returns and drafted snapshots appear here once compliance work starts."/>
                    @else
                        <ul class="mt-3 space-y-2.5">
                            @foreach ($latestFilings as $filing)
                                <li class="flex items-center justify-between gap-2 text-sm">
                                    <span class="text-slate-700">GSTR-{{ strtoupper($filing->type) === 'GSTR3B' ? '3B' : '1' }} · {{ $filing->period }}</span>
                                    <x-badge status="filed">Filed</x-badge>
                                </li>
                            @endforeach
                            @foreach ($latestDrafts as $snapshot)
                                <li class="flex items-center justify-between gap-2 text-sm">
                                    <span class="text-slate-700">GSTR-{{ strtoupper($snapshot->type) === 'GSTR3B' ? '3B' : '1' }} · {{ $snapshot->period }}</span>
                                    <x-badge status="draft">Draft</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="mt-3 text-sm text-slate-500">Compliance data is available to users with GST returns access.</p>
                @endcan
            </section>
        </div>
    </div>
</div>
