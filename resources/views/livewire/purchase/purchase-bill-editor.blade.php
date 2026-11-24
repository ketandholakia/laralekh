<div x-data>
    <x-page-header
        :title="$billId ? 'Edit purchase bill draft' : 'New purchase bill'"
        eyebrow="Purchases"
        :sub="'Enter inward supply details, tax breakdown, and ITC eligibility'"
    >
        <x-button :href="route('purchases.index')" variant="ghost">Back to list</x-button>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success" class="mb-6">{{ session('status') }}</x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Supplier & Bill Details --}}
            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Supplier & Bill details</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Supplier" name="supplier">
                        <x-select name="supplier" wire:model="data.party_id">
                            <option value="">Select supplier</option>
                            @foreach($parties as $party)
                                <option value="{{ $party->id }}" @selected($party->id == ($data['party_id'] ?? ''))>{{ $party->name }}</option>
                            @endforeach
                        </x-select>
                    </x-field>

                    <x-field label="Supplier bill number" name="supplier-bill-no">
                        <x-input name="supplier-bill-no" type="text" wire:model="data.supplier_bill_number" placeholder="e.g. INV-12345" />
                    </x-field>

                    <x-field label="Supplier bill date" name="supplier-bill-date">
                        <x-input name="supplier-bill-date" type="date" wire:model="data.supplier_bill_date" />
                    </x-field>

                    <x-field label="Posting/tax date" name="posting-date">
                        <x-input name="posting-date" type="date" wire:model="data.date" />
                    </x-field>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <label class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" wire:model="data.reverse_charge" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600/20">
                        <span>Reverse charge (professional review required)</span>
                    </label>
                </div>
            </x-card>

            {{-- Line Items --}}
            <x-card class="space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h2 class="text-sm font-semibold text-slate-900">Line items</h2>
                    <span class="text-xs text-slate-500">{{ count($lines) }} line(s)</span>
                </div>

                @foreach($lines as $index => $line)
                    <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-4 space-y-3">
                        <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <span>Line #{{ $index + 1 }}</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-500">Description</label>
                                <input type="text" placeholder="Description" wire:model="lines.{{ $index }}.description"
                                       class="mt-1 w-full rounded-md border-slate-300 px-2.5 py-1.5 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">HSN/SAC</label>
                                <input type="text" placeholder="HSN/SAC" wire:model="lines.{{ $index }}.hsn_sac"
                                       class="mt-1 w-full rounded-md border-slate-300 px-2.5 py-1.5 font-mono text-xs focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Quantity</label>
                                <input type="text" placeholder="1" wire:model="lines.{{ $index }}.quantity"
                                       class="mt-1 w-full rounded-md border-slate-300 px-2.5 py-1.5 text-right font-mono text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Unit price (₹)</label>
                                <input type="text" placeholder="0.00" wire:model="lines.{{ $index }}.unit_price"
                                       class="mt-1 w-full rounded-md border-slate-300 px-2.5 py-1.5 text-right font-mono text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">Cost ledger</label>
                                <select wire:model="lines.{{ $index }}.purchase_ledger_id"
                                        class="mt-1 w-full rounded-md border-slate-300 px-2 py-1.5 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                                    <option value="">Cost ledger</option>
                                    @foreach($ledgers as $ledger)
                                        <option value="{{ $ledger->id }}">{{ $ledger->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">GST rate</label>
                                <select wire:model="lines.{{ $index }}.tax_rate_id"
                                        class="mt-1 w-full rounded-md border-slate-300 px-2 py-1.5 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                                    <option value="">GST rate</option>
                                    @foreach($rates as $rate)
                                        <option value="{{ $rate->id }}">{{ $rate->code }} ({{ $rate->rate }}%){{ $rate->is_verified ? '' : ' — UNVERIFIED' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">ITC eligibility</label>
                                <select wire:model="lines.{{ $index }}.itc_eligibility"
                                        class="mt-1 w-full rounded-md border-slate-300 px-2 py-1.5 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                                    <option value="eligible">ITC eligible</option>
                                    <option value="blocked">Blocked ITC</option>
                                    <option value="ineligible_reversal_review">Review/reversal</option>
                                </select>
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-xs font-medium text-slate-500">ITC reason / Section 17(5) note</label>
                                <input type="text" placeholder="Reason (e.g. Section 17(5) motor vehicle)" wire:model="lines.{{ $index }}.itc_reason"
                                       class="mt-1 w-full rounded-md border-slate-300 px-2.5 py-1.5 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                            </div>
                        </div>
                    </div>
                @endforeach

                <x-button type="button" wire:click="addLine" variant="secondary" size="sm">
                    <x-icon name="plus" class="h-4 w-4" /> Add line
                </x-button>
            </x-card>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-6">
            <x-card class="space-y-3">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Actions</h2>
                <x-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save" variant="secondary" class="w-full">
                    Save draft
                </x-button>
                @can('purchases.post')
                    <x-button type="button" wire:click="post" wire:loading.attr="disabled" wire:target="post"
                              wire:confirm="Post this purchase bill? Accounting vouchers will be created and inventory updated."
                              variant="primary" class="w-full">
                        Save and post
                    </x-button>
                @endcan
                <p class="text-xs text-slate-400 text-center">Posting creates ledger entries, verifies ITC eligibility rules, and updates stock movements.</p>
            </x-card>
        </aside>
    </div>
</div>
