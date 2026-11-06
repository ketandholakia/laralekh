<div x-data>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">{{ $invoiceId ? 'Edit draft invoice' : 'New sales invoice' }}</h1>
        <a href="{{ route('invoices.index') }}" class="text-sm text-slate-500 hover:underline">Back to list</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            {{-- Party --}}
            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-3">Customer</h2>
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="partyQuery" placeholder="Search party by name or GSTIN"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    @if (count($this->partyOptions) > 0)
                        <ul class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded shadow-lg max-h-60 overflow-auto">
                            @foreach ($this->partyOptions as $option)
                                <li>
                                    <button type="button" wire:click="selectParty({{ $option['id'] }})"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50">
                                        {{ $option['name'] }}
                                        @if ($option['gstin'])<span class="text-slate-400 font-mono text-xs"> · {{ $option['gstin'] }}</span>@endif
                                        @if ($option['state_code'])<span class="text-slate-400 text-xs"> · {{ $option['state_code'] }}</span>@endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                @if ($partyId)<p class="mt-2 text-sm text-green-700">Selected: <strong>{{ $partyName }}</strong> <button type="button" wire:click="$set('partyId', null)" wire:click="$set('partyQuery','')" class="text-xs text-slate-400 hover:underline">(change)</button></p>@endif
                @error('lines')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </section>

            {{-- Items --}}
            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-3">Items</h2>
                <div class="relative mb-4">
                    <input type="text" wire:model.live.debounce.300ms="itemQuery" placeholder="Search item by name, SKU or HSN/SAC to add a line"
                           class="w-full rounded border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    @if (count($this->itemOptions) > 0)
                        <ul class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded shadow-lg max-h-60 overflow-auto">
                            @foreach ($this->itemOptions as $option)
                                <li>
                                    <button type="button" wire:click="addItem({{ $option['id'] }})"
                                            class="w-full text-left px-3 py-2 text-sm hover:bg-indigo-50">
                                        {{ $option['name'] }} <span class="text-slate-400 text-xs">{{ $option['sku'] }} · {{ $option['hsn_sac'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if (count($this->salesLedgers) === 0)
                    <p class="text-sm text-red-600">No income ledgers exist yet; create the chart of accounts first.</p>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="py-1 pr-2">Description</th>
                                <th class="py-1 pr-2">HSN/SAC</th>
                                <th class="py-1 pr-2 w-24">Qty</th>
                                <th class="py-1 pr-2 w-28">Price (excl.)</th>
                                <th class="py-1 pr-2 w-24">Discount</th>
                                <th class="py-1 pr-2">Category</th>
                                <th class="py-1 pr-2">GST rate</th>
                                <th class="py-1 pr-2">Sales ledger</th>
                                <th class="py-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $index => $line)
                                <tr class="border-t border-slate-100 align-top">
                                    <td class="py-1 pr-2"><input wire:model.live="lines.{{ $index }}.description" class="w-full min-w-32 rounded border border-slate-300 px-2 py-1 text-sm"></td>
                                    <td class="py-1 pr-2"><input wire:model.live="lines.{{ $index }}.hsn_sac" class="w-20 rounded border border-slate-300 px-2 py-1 text-sm"></td>
                                    <td class="py-1 pr-2"><input wire:model.live="lines.{{ $index }}.quantity" class="w-full rounded border border-slate-300 px-2 py-1 text-sm"></td>
                                    <td class="py-1 pr-2"><input wire:model.live="lines.{{ $index }}.unit_price" class="w-full rounded border border-slate-300 px-2 py-1 text-sm"></td>
                                    <td class="py-1 pr-2"><input wire:model.live="lines.{{ $index }}.discount" class="w-full rounded border border-slate-300 px-2 py-1 text-sm"></td>
                                    <td class="py-1 pr-2">
                                        <select wire:model.live="lines.{{ $index }}.tax_category" class="rounded border border-slate-300 px-1 py-1 text-sm">
                                            @foreach (['taxable', 'nil_rated', 'exempt', 'non_gst'] as $cat)
                                                <option value="{{ $cat }}">{{ str_replace('_', ' ', $cat) }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-1 pr-2">
                                        <select wire:model.live="lines.{{ $index }}.tax_rate_id" @disabled($line['tax_category'] !== 'taxable')
                                                class="rounded border border-slate-300 px-1 py-1 text-sm max-w-36">
                                            <option value="">—</option>
                                            @foreach ($this->rateOptions as $rate)
                                                <option value="{{ $rate['id'] }}" @selected((int) $line['tax_rate_id'] === $rate['id'])>{{ $rate['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-1 pr-2">
                                        <select wire:model.live="lines.{{ $index }}.sales_ledger_id" class="rounded border border-slate-300 px-1 py-1 text-sm max-w-36">
                                            @foreach ($this->salesLedgers as $ledger)
                                                <option value="{{ $ledger->id }}" @selected((int) $line['sales_ledger_id'] === $ledger->id)>{{ $ledger->code }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="py-1"><button type="button" wire:click="removeLine({{ $index }})" class="text-red-500 hover:underline text-xs">✕</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="button" wire:click="addBlankLine" class="mt-3 text-sm text-indigo-600 hover:underline">+ Add blank line</button>
                <div wire:loading class="text-xs text-slate-400 mt-2">Updating totals…</div>
            </section>

            {{-- GST treatment --}}
            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-3">GST treatment</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Supply type</label>
                        <select wire:model.live="supplyType" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="domestic">Domestic</option>
                            <option value="export">Export</option>
                            <option value="sez">SEZ</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Place of supply</label>
                        <select wire:model.live="placeOfSupply" @disabled($supplyType === 'export')
                                class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="">— choose —</option>
                            @foreach ($this->states as $state)
                                <option value="{{ $state->code }}">{{ $state->code }} · {{ $state->name }}</option>
                            @endforeach
                        </select>
                        @if ($supplyType === 'export')
                            <p class="mt-1 text-xs text-slate-400">Exports use the overseas code 99 automatically.</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Zero-rated route (export/SEZ)</label>
                        <select wire:model.live="zeroRatedMode" @disabled($supplyType === 'domestic')
                                class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                            <option value="none">Not zero-rated</option>
                            <option value="lut">Under LUT (no IGST)</option>
                            <option value="with_payment">With IGST payment</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="reverseCharge" class="rounded border-slate-300"> Reverse charge (recipient pays tax)</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="zeroRatedEligible" class="rounded border-slate-300"> Zero-rated eligibility verified</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="sezAuthorized" class="rounded border-slate-300"> SEZ authorised operations</label>
                </div>
            </section>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-4">
            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-3">Invoice details</h2>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Invoice date</label>
                        <input type="date" wire:model.live="date" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Due date</label>
                        <input type="date" wire:model="dueDate" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Series</label>
                        <input type="text" wire:model="series" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm uppercase">
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-lg border border-slate-200 p-4">
                <h2 class="text-sm font-semibold mb-3">Live tax breakup</h2>
                @if ($this->preview['error'])
                    <p class="text-sm text-amber-700">{{ $this->preview['error'] }}</p>
                @elseif ($this->preview['totals'] === null)
                    <p class="text-sm text-slate-400">Add items to see totals.</p>
                @else
                    @php $t = $this->preview['totals']; @endphp
                    <dl class="text-sm space-y-1">
                        <div class="flex justify-between"><dt>Taxable value</dt><dd class="font-mono">₹{{ $t['taxable'] }}</dd></div>
                        @if ((float) $t['cgst'] !== 0.0 || (float) $t['sgst'] !== 0.0 || (float) $t['utgst'] !== 0.0)
                            <div class="flex justify-between"><dt>CGST</dt><dd class="font-mono">₹{{ $t['cgst'] }}</dd></div>
                            @if ((float) $t['sgst'] !== 0.0)<div class="flex justify-between"><dt>SGST</dt><dd class="font-mono">₹{{ $t['sgst'] }}</dd></div>@endif
                            @if ((float) $t['utgst'] !== 0.0)<div class="flex justify-between"><dt>UTGST</dt><dd class="font-mono">₹{{ $t['utgst'] }}</dd></div>@endif
                        @endif
                        @if ((float) $t['igst'] !== 0.0)
                            <div class="flex justify-between"><dt>IGST</dt><dd class="font-mono">₹{{ $t['igst'] }}</dd></div>
                        @endif
                        @if (!\App\Domain\Shared\ValueObjects\Money::of($t['cess'])->isZero())
                            <div class="flex justify-between"><dt>Compensation cess</dt><dd class="font-mono">₹{{ $t['cess'] }}</dd></div>
                        @endif
                        @if ((float) $t['recipient_tax'] !== 0.0)
                            <div class="flex justify-between text-slate-500"><dt>Tax under RCM (recipient)</dt><dd class="font-mono">₹{{ $t['recipient_tax'] }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-slate-200 pt-2 mt-2 font-semibold"><dt>Total</dt><dd class="font-mono">₹{{ $t['total'] }}</dd></div>
                    </dl>
                @endif
            </section>

            <section class="bg-white rounded-lg border border-slate-200 p-4 space-y-2">
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="w-full rounded border border-indigo-600 px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50 disabled:opacity-50">Save draft</button>
                @can('invoices.post')
                <button type="button" wire:click="post" wire:loading.attr="disabled" wire:target="post"
                        wire:confirm="Post this invoice? Posted invoices cannot be modified and consume the next invoice number."
                        class="w-full rounded bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50">Post invoice</button>
                @endcan
                <p class="text-xs text-slate-400">Posting validates effective-dated GST rates, allocates the next number and is final.</p>
            </section>
        </aside>
    </div>
</div>
