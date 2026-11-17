<div>
    <x-page-header title="Balance sheet" eyebrow="Reports" :sub="'Cumulative posted balances as at the selected date'">
        <x-field label="As at" name="bs-until">
            <x-input name="bs-until" type="date" wire:model.live="until"/>
        </x-field>
    </x-page-header>

    @if ($report['assets']->isZero() && $report['liabilities']->isZero() && $report['equity']->isZero() && $report['retained']->isZero())
        <x-card>
            <x-empty-state icon="invoice" title="No posted movements yet"
                           message="The balance sheet lists ledgers once documents are posted."/>
        </x-card>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @foreach (['Assets' => 'Assets', 'Liabilities' => 'Liabilities', 'Equity' => 'Equity'] as $key => $heading)
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
                    <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">{{ $heading }}</div>
                    <table class="w-full text-sm">
                        <tbody>
                            @forelse ($report['sections'][$key] as $row)
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 py-2.5">
                                        <span class="font-mono text-xs text-slate-500">{{ $row['code'] }}</span>
                                        <span class="ml-1 text-slate-800">{{ $row['name'] }}</span>
                                    </td>
                                    <td class="px-5 py-2.5 text-right"><x-money :value="$row['balance']"/></td>
                                </tr>
                            @empty
                                <tr><td class="px-5 py-4 text-sm text-slate-500">No balances.</td></tr>
                            @endforelse
                            <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                                <td class="px-5 py-2.5 text-slate-900">Total {{ strtolower($heading) }}</td>
                                <td class="px-5 py-2.5 text-right"><x-money :value="$report[strtolower($key)]"/></td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-kpi label="Retained profit (income − expense)"><x-money :value="$report['retained']"/></x-kpi>
            <x-kpi label="Liabilities + equity + retained profit">
                <x-money :value="$report['liabilities']->add($report['equity'])->add($report['retained'])"/>
            </x-kpi>
            <x-kpi label="Reconciliation">
                @if ($report['balanced'])
                    <span class="inline-flex items-center gap-1.5 text-green-700"><x-icon name="check-circle" class="h-5 w-5"/> Balanced</span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-red-700"><x-icon name="alert" class="h-5 w-5"/> Out of balance</span>
                @endif
            </x-kpi>
        </div>
    @endif
</div>
