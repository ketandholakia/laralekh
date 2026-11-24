<div x-data>
    <x-page-header
        :title="$invoiceId ? 'Edit draft invoice' : 'New sales invoice'"
        eyebrow="Sales"
        :sub="'Create and post GST-compliant sales invoices with live tax preview'"
    >
        <x-button :href="route('invoices.index')" variant="ghost">Back to list</x-button>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Customer --}}
            <x-card>
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Customer</h2>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="partyQuery" placeholder="Search party by name or GSTIN"
                           class="w-full rounded-md border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                    @if (count($this->partyOptions) > 0)
                        <ul class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-pop">
                            @foreach ($this->partyOptions as $option)
                                <li>
                                    <button type="button" wire:click="selectParty({{ $option['id'] }})"
                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-brand-50">
                                        <div>
                                            <span class="font-medium text-slate-900">{{ $option['name'] }}</span>
                                            @if ($option['state_code'])<span class="text-xs text-slate-400"> · State {{ $option['state_code'] }}</span>@endif
                                        </div>
                                        @if ($option['gstin'])<span class="font-mono text-xs text-slate-500">{{ $option['gstin'] }}</span>@endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($partyId)
                    <div class="mt-2.5 flex items-center justify-between rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-900">
                        <span>Selected: <strong>{{ $partyName }}</strong></span>
                        <button type="button" wire:click="$set('partyId', null); $set('partyQuery', '')" class="text-xs text-slate-500 hover:text-slate-800 hover:underline">(change)</button>
                    </div>
                @endif
                @error('lines')
                    <x-alert type="danger" class="mt-3">{{ $message }}</x-alert>
                @enderror
            </x-card>

            {{-- Items --}}
            <x-card>
                <h2 class="mb-3 text-sm font-semibold text-slate-900">Items</h2>
                <div class="relative mb-4">
                    <input type="text" wire:model.live.debounce.300ms="itemQuery" placeholder="Search item by name, SKU or HSN/SAC to add a line"
                           class="w-full rounded-md border-slate-300 px-3 py-2 text-sm focus:border-brand-600 focus:ring-2 focus:ring-brand-600/20 focus:outline-none">
                    @if (count($this->itemOptions) > 0)
                        <ul class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md border border-slate-200 bg-white shadow-pop">
                            @foreach ($this->itemOptions as $option)
                                <li>
                                    <button type="button" wire:click="addItem({{ $option['id'] }})"
                                            class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-brand-50">
                                        <div>
                                            <span class="font-medium text-slate-900">{{ $option['name'] }}</span>
                                            <span class="text-xs text-slate-400"> · {{ $option['sku'] }} · {{ $option['hsn_sac'] }}</span>
                                        </div>
                                        @if ($option['sale_price'])
                                            <span class="font-mono text-xs text-slate-600">₹{{ $option['sale_price'] }}</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if (count($this->salesLedgers) === 0)
                    <x-alert type="danger" class="mb-4">No income ledgers exist yet; create the chart of accounts first.</x-alert>
                @endif

                <x-table caption="Invoice line items">
                    <thead>
                        <tr>
                            <th scope="col">Description</th>
                            <th scope="col" class="w-24">HSN/SAC</th>
                            <th scope="col" class="w-24 text-right">Qty</th>
                            <th scope="col" class="w-28 text-right">Price (excl.)</th>
                            <th scope="col" class="w-24 text-right">Discount</th>
                            <th scope="col">Category</th>
                            <th scope="col">GST rate</th>
                            <th scope="col">Sales ledger</th>
                            <th scope="col" class="w-10"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($lines as $index => $line)
                            <tr class="align-top">
                                <td>
                                    <input type="text" wire:model.live="lines.{{ $index }}.description"
                                           class="w-full min-w-32 rounded-md border-slate-300 px-2.5 py-1.5 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none"
                                           placeholder="Description">
                                </td>
                                <td>
                                    <input type="text" wire:model.live="lines.{{ $index }}.hsn_sac"
                                           class="w-20 rounded-md border-slate-300 px-2.5 py-1.5 font-mono text-xs focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none"
                                           placeholder="HSN/SAC">
                                </td>
                                <td>
                                    <input type="text" wire:model.live="lines.{{ $index }}.quantity"
                                           class="w-full rounded-md border-slate-300 px-2.5 py-1.5 text-right font-mono text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none"
                                           placeholder="1">
                                </td>
                                <td>
                                    <input type="text" wire:model.live="lines.{{ $index }}.unit_price"
                                           class="w-full rounded-md border-slate-300 px-2.5 py-1.5 text-right font-mono text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none"
                                           placeholder="0.00">
                                </td>
                                <td>
                                    <input type="text" wire:model.live="lines.{{ $index }}.discount"
                                           class="w-full rounded-md border-slate-300 px-2.5 py-1.5 text-right font-mono text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none"
                                           placeholder="0.00">
                                </td>
                                <td>
                                    <select wire:model.live="lines.{{ $index }}.tax_category"
                                            class="rounded-md border-slate-300 px-2 py-1.5 text-sm focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                                        @foreach (['taxable', 'nil_rated', 'exempt', 'non_gst'] as $cat)
                                            <option value="{{ $cat }}">{{ str_replace('_', ' ', $cat) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select wire:model.live="lines.{{ $index }}.tax_rate_id" @disabled($line['tax_category'] !== 'taxable')
                                            class="rounded-md border-slate-300 px-2 py-1.5 text-sm max-w-36 focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none disabled:bg-slate-50 disabled:text-slate-400">
                                        <option value="">—</option>
                                        @foreach ($this->rateOptions as $rate)
                                            <option value="{{ $rate['id'] }}" @selected((int) $line['tax_rate_id'] === $rate['id'])>{{ $rate['label'] }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select wire:model.live="lines.{{ $index }}.sales_ledger_id"
                                            class="rounded-md border-slate-300 px-2 py-1.5 text-sm max-w-36 focus:border-brand-600 focus:ring-1 focus:ring-brand-600 focus:outline-none">
                                        @foreach ($this->salesLedgers as $ledger)
                                            <option value="{{ $ledger->id }}" @selected((int) $line['sales_ledger_id'] === $ledger->id)>{{ $ledger->code }} · {{ $ledger->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="text-center">
                                    <button type="button" wire:click="removeLine({{ $index }})"
                                            class="inline-flex items-center justify-center rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600"
                                            title="Remove line" aria-label="Remove line">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-table>
                <div class="mt-3 flex items-center justify-between">
                    <x-button type="button" wire:click="addBlankLine" variant="secondary" size="sm">
                        <x-icon name="plus" class="h-4 w-4" /> Add blank line
                    </x-button>
                    <div wire:loading wire:target="lines,addItem,addBlankLine,removeLine" class="text-xs text-slate-400">Updating totals…</div>
                </div>
            </x-card>

            {{-- GST treatment --}}
            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">GST treatment</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <x-field label="Supply type" name="supply-type">
                        <x-select name="supply-type" wire:model.live="supplyType">
                            <option value="domestic">Domestic</option>
                            <option value="export">Export</option>
                            <option value="sez">SEZ</option>
                        </x-select>
                    </x-field>
                    <x-field label="Place of supply" name="place-of-supply">
                        <x-select name="place-of-supply" wire:model.live="placeOfSupply" :disabled="$supplyType === 'export'">
                            <option value="">— choose —</option>
                            @foreach ($this->states as $state)
                                <option value="{{ $state->code }}">{{ $state->code }} · {{ $state->name }}</option>
                            @endforeach
                        </x-select>
                        @if ($supplyType === 'export')
                            <p class="mt-1 text-xs text-slate-400">Exports use the overseas code 99 automatically.</p>
                        @endif
                    </x-field>
                    <x-field label="Zero-rated route (export/SEZ)" name="zero-rated-mode">
                        <x-select name="zero-rated-mode" wire:model.live="zeroRatedMode" :disabled="$supplyType === 'domestic'">
                            <option value="none">Not zero-rated</option>
                            <option value="lut">Under LUT (no IGST)</option>
                            <option value="with_payment">With IGST payment</option>
                        </x-select>
                    </x-field>
                </div>
                <div class="pt-2 border-t border-slate-100 flex flex-wrap gap-6 text-sm">
                    <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                        <input type="checkbox" wire:model.live="reverseCharge" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600/20">
                        <span>Reverse charge (recipient pays tax)</span>
                    </label>
                    <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                        <input type="checkbox" wire:model.live="zeroRatedEligible" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600/20">
                        <span>Zero-rated eligibility verified</span>
                    </label>
                    <label class="flex items-center gap-2 text-slate-700 cursor-pointer">
                        <input type="checkbox" wire:model.live="sezAuthorized" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600/20">
                        <span>SEZ authorised operations</span>
                    </label>
                </div>
            </x-card>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-6">
            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Invoice details</h2>
                <div class="space-y-3">
                    <x-field label="Invoice date" name="date">
                        <x-input name="date" type="date" wire:model.live="date" />
                    </x-field>
                    <x-field label="Due date" name="due-date">
                        <x-input name="due-date" type="date" wire:model="dueDate" />
                    </x-field>
                    <x-field label="Series" name="series">
                        <x-input name="series" type="text" wire:model="series" class="uppercase" />
                    </x-field>
                </div>
            </x-card>

            <x-card>
                <h2 class="mb-3 text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Live tax breakup</h2>
                @if ($this->preview['error'])
                    <x-alert type="warning">{{ $this->preview['error'] }}</x-alert>
                @elseif ($this->preview['totals'] === null)
                    <p class="text-sm text-slate-400">Add items to see totals.</p>
                @else
                    @php $t = $this->preview['totals']; @endphp
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between text-slate-600"><dt>Taxable value</dt><dd class="font-mono tabular-nums text-slate-900">₹{{ $t['taxable'] }}</dd></div>
                        @if ((float) $t['cgst'] !== 0.0 || (float) $t['sgst'] !== 0.0 || (float) $t['utgst'] !== 0.0)
                            <div class="flex justify-between text-slate-600"><dt>CGST</dt><dd class="font-mono tabular-nums text-slate-900">₹{{ $t['cgst'] }}</dd></div>
                            @if ((float) $t['sgst'] !== 0.0)<div class="flex justify-between text-slate-600"><dt>SGST</dt><dd class="font-mono tabular-nums text-slate-900">₹{{ $t['sgst'] }}</dd></div>@endif
                            @if ((float) $t['utgst'] !== 0.0)<div class="flex justify-between text-slate-600"><dt>UTGST</dt><dd class="font-mono tabular-nums text-slate-900">₹{{ $t['utgst'] }}</dd></div>@endif
                        @endif
                        @if ((float) $t['igst'] !== 0.0)
                            <div class="flex justify-between text-slate-600"><dt>IGST</dt><dd class="font-mono tabular-nums text-slate-900">₹{{ $t['igst'] }}</dd></div>
                        @endif
                        @if (!\App\Domain\Shared\ValueObjects\Money::of($t['cess'])->isZero())
                            <div class="flex justify-between text-slate-600"><dt>Compensation cess</dt><dd class="font-mono tabular-nums text-slate-900">₹{{ $t['cess'] }}</dd></div>
                        @endif
                        @if ((float) $t['recipient_tax'] !== 0.0)
                            <div class="flex justify-between text-slate-500"><dt>Tax under RCM (recipient)</dt><dd class="font-mono tabular-nums text-slate-500">₹{{ $t['recipient_tax'] }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-slate-200 pt-2.5 mt-2 font-semibold text-slate-900"><dt>Total</dt><dd class="font-mono tabular-nums text-brand-700 text-base">₹{{ $t['total'] }}</dd></div>
                    </dl>
                @endif
            </x-card>

            <x-card class="space-y-3">
                <x-button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                          variant="secondary" class="w-full">
                    Save draft
                </x-button>
                @can('invoices.post')
                    <x-button type="button" wire:click="post" wire:loading.attr="disabled" wire:target="post"
                              wire:confirm="Post this invoice? Posted invoices cannot be modified and consume the next invoice number."
                              variant="primary" class="w-full">
                        Post invoice
                    </x-button>
                @endcan
                <p class="text-xs text-slate-400 text-center">Posting validates effective-dated GST rates, allocates the next number and is final.</p>
            </x-card>
        </aside>
    </div>
</div>
