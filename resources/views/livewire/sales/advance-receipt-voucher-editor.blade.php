<div class="space-y-6">
    <x-page-header
        :title="$voucherId ? 'Edit advance receipt voucher draft' : 'Issue statutory advance receipt voucher'"
        eyebrow="Sales · Statutory GST Documents"
        sub="Issued under CGST Act s. 31(3)(d) & Rule 50 for advance payments received before supply"
    >
        @if ($partyId)
            <x-button :href="route('customers.show', $partyId)" variant="ghost">Customer outstanding</x-button>
        @endif
    </x-page-header>

    @error('grossAmount')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            {{-- Customer Card --}}
            <x-card>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Customer</span>
                        <span class="mt-1 block text-base font-bold text-slate-900">{{ $partyName }}</span>
                    </div>
                    @if ($partyId)
                        <x-button :href="route('customers.statement', $partyId)" variant="secondary" size="sm">Statement</x-button>
                    @endif
                </div>
            </x-card>

            {{-- Advance Particulars --}}
            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Voucher Particulars</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-field label="Voucher date" name="date">
                        <x-input name="date" type="date" wire:model.live="date" />
                        @error('date')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Place of supply (State)" name="placeOfSupply">
                        <x-select name="placeOfSupply" wire:model.live="placeOfSupply">
                            @foreach ($states as $state)
                                <option value="{{ $state->code }}" @selected($state->code === $placeOfSupply)>{{ $state->code }} · {{ $state->name }}</option>
                            @endforeach
                        </x-select>
                        @error('placeOfSupply')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Supply type" name="supplyType">
                        <x-select name="supplyType" wire:model.live="supplyType">
                            <option value="services">Services (Advance Tax applies)</option>
                            <option value="goods">Goods (Exempt under Notif 66/2017-CT)</option>
                        </x-select>
                        @error('supplyType')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Linked receipt (Optional)" name="receiptId">
                        <x-select name="receiptId" wire:model.live="receiptId">
                            <option value="">— Standalone advance voucher —</option>
                            @foreach ($availableReceipts as $rec)
                                <option value="{{ $rec['id'] }}" @selected($rec['id'] === $receiptId)>{{ $rec['label'] }}</option>
                            @endforeach
                        </x-select>
                        @error('receiptId')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <div class="sm:col-span-2">
                        <x-field label="Description of supply (Services / Goods)" name="description">
                            <x-input name="description" type="text" wire:model="description" placeholder="e.g. Advance towards software development & consulting services" />
                            @error('description')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                        </x-field>
                    </div>

                    <x-field label="Payment reference (UTR / Cheque no.)" name="paymentReference">
                        <x-input name="paymentReference" type="text" wire:model="paymentReference" placeholder="e.g. UTR-987654" />
                        @error('paymentReference')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Narration / Notes" name="narration">
                        <x-input name="narration" type="text" wire:model="narration" placeholder="Internal remarks" />
                        @error('narration')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>
                </div>
            </x-card>

            {{-- Statutory Rule 50 Determination Card --}}
            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Statutory Rule 50 Determinations</h2>
                <div class="space-y-3">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model.live="isRateDeterminable" class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                        <div>
                            <span class="block text-sm font-medium text-slate-900">Tax rate is determinable</span>
                            <span class="block text-xs text-slate-500">Uncheck if the GST rate cannot be determined at the time of advance. Rule 50 proviso (i) mandates a statutory 18% rate fallback.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model.live="isInterstateDeterminable" class="mt-1 rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                        <div>
                            <span class="block text-sm font-medium text-slate-900">Place of supply / nature is determinable</span>
                            <span class="block text-xs text-slate-500">Uncheck if the destination or nature of supply is undetermined. Rule 50 proviso (ii) mandates inter-state supply (IGST) treatment.</span>
                        </div>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-2 border-t border-slate-100">
                    <x-field label="Gross advance received (₹)" name="grossAmount">
                        <x-input name="grossAmount" type="text" wire:model.live.debounce.300ms="grossAmount" placeholder="e.g. 11800.00" />
                        @error('grossAmount')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>

                    <x-field label="Tax rate (%)" name="taxRate">
                        <x-input name="taxRate" type="text" wire:model.live.debounce.300ms="taxRate" :disabled="!$isRateDeterminable || $supplyType === 'goods'" />
                        <span class="text-xs text-slate-500 mt-1 block">
                            @if ($supplyType === 'goods')
                                Goods advances exempt from advance tax
                            @elseif (!$isRateDeterminable)
                                Statutory 18% rate locked by Rule 50 proviso (i)
                            @else
                                Applicable GST rate
                            @endif
                        </span>
                        @error('taxRate')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- Summary Sidebar --}}
        <div class="space-y-6">
            <x-card class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900 border-b border-slate-100 pb-2">Advance Tax Computation</h2>

                <div class="space-y-2 text-sm">
                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-600">Gross Advance</span>
                        <span class="font-mono font-bold text-slate-900">₹{{ $grossAmount !== '' ? $grossAmount : '0.00' }}</span>
                    </div>

                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-600">Effective GST Rate</span>
                        <span class="font-mono text-slate-900">{{ $taxPreview['effective_rate'] }}%</span>
                    </div>

                    <div class="flex justify-between py-1 border-b border-slate-100">
                        <span class="text-slate-600">Back-calculated Taxable</span>
                        <span class="font-mono font-medium text-slate-900">₹{{ $taxPreview['taxable'] }}</span>
                    </div>

                    @if ((float) $taxPreview['cgst'] > 0)
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-600">CGST</span>
                            <span class="font-mono text-slate-900">₹{{ $taxPreview['cgst'] }}</span>
                        </div>
                    @endif

                    @if ((float) $taxPreview['sgst'] > 0)
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-600">SGST</span>
                            <span class="font-mono text-slate-900">₹{{ $taxPreview['sgst'] }}</span>
                        </div>
                    @endif

                    @if ((float) $taxPreview['utgst'] > 0)
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-600">UTGST</span>
                            <span class="font-mono text-slate-900">₹{{ $taxPreview['utgst'] }}</span>
                        </div>
                    @endif

                    @if ((float) $taxPreview['igst'] > 0)
                        <div class="flex justify-between py-1 border-b border-slate-100">
                            <span class="text-slate-600">IGST</span>
                            <span class="font-mono text-slate-900">₹{{ $taxPreview['igst'] }}</span>
                        </div>
                    @endif

                    <div class="flex justify-between py-2 border-t-2 border-slate-900 font-bold">
                        <span class="text-slate-900">Total Statutory Tax</span>
                        <span class="font-mono text-emerald-700">₹{{ $taxPreview['total_tax'] }}</span>
                    </div>
                </div>

                <div class="space-y-3 pt-3">
                    @can('receipts.post')
                        <x-button type="button" wire:click="postVoucher" variant="primary" class="w-full justify-center">
                            Post Statutory Receipt Voucher
                        </x-button>
                    @endcan

                    <x-button type="button" wire:click="saveDraft" variant="secondary" class="w-full justify-center">
                        Save Draft Voucher
                    </x-button>
                </div>
            </x-card>

            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600 space-y-2">
                <span class="font-semibold block text-slate-800">Legal Compliance Note</span>
                <p>This voucher satisfies Section 31(3)(d) of the CGST Act, 2017. Outward supplies of services attract time of supply upon advance receipt. Advances for goods remain exempt from tax payment under Notification No. 66/2017-CT.</p>
            </div>
        </div>
    </div>
</div>
