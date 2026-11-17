<div>
    <x-page-header title="Profit &amp; loss" eyebrow="Reports"
                   :sub="'Income and expense movements for '.$report['from'].' to '.$report['until'].' (inclusive)'">
        <div class="flex items-end gap-2">
            <x-field label="From" name="pl-from">
                <x-input name="pl-from" type="date" wire:model.live="from"/>
            </x-field>
            <x-field label="Until" name="pl-until">
                <x-input name="pl-until" type="date" wire:model.live="until"/>
            </x-field>
        </div>
    </x-page-header>

    @if (count($report['rows']) === 0)
        <x-card>
            <x-empty-state icon="invoice" title="No income or expense movements yet"
                           message="The profit & loss statement lists ledgers once documents are posted."/>
        </x-card>
    @else
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            @foreach (['income' => 'Income', 'expense' => 'Expenses'] as $nature => $heading)
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-card">
                    <div class="border-b border-slate-200 px-5 py-3 text-sm font-semibold text-slate-900">{{ $heading }}</div>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($report['rows'] as $row)
                                @continue($row['nature'] !== $nature)
                                <tr class="border-t border-slate-100">
                                    <td class="px-5 py-2.5">
                                        <span class="font-mono text-xs text-slate-500">{{ $row['code'] }}</span>
                                        <span class="ml-1 text-slate-800">{{ $row['name'] }}</span>
                                    </td>
                                    <td class="px-5 py-2.5 text-right"><x-money :value="$row['amount']"/></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endforeach
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-kpi label="Income"><x-money :value="$report['income']"/></x-kpi>
            <x-kpi label="Expenses"><x-money :value="$report['expense']"/></x-kpi>
            <x-kpi label="{{ $report['net']->isNegative() ? 'Net loss' : 'Net profit' }}">
                <x-money :value="$report['net']"/>
            </x-kpi>
        </div>
    @endif
</div>
