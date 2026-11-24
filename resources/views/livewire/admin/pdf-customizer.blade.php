<div class="space-y-6">
    <x-page-header
        title="PDF Template & Letterhead"
        eyebrow="Administration · Settings"
        sub="Configure company letterhead, logo, bank remittance details, and footer notes for generated PDF documents."
    />

    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif

    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            {{-- Left column: Settings Form --}}
            <div class="space-y-6 lg:col-span-7">
                {{-- Letterhead & Header Card --}}
                <x-card>
                    <h2 class="mb-4 text-base font-semibold text-slate-900 border-b border-slate-100 pb-2">Header & Letterhead</h2>
                    
                    <div class="space-y-4">
                        <label class="flex items-start gap-3 rounded-md border border-slate-200 bg-slate-50/50 p-3 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" wire:model.live="hide_header" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <div class="text-xs">
                                <span class="font-medium text-slate-800">Print on Pre-Printed Stationery (Hide Company Header)</span>
                                <p class="text-slate-500 mt-0.5">When checked, the company header block is omitted on invoices, leaving a standard top margin to feed physical pre-printed letterhead paper.</p>
                            </div>
                        </label>

                        @if (!$hide_header)
                            <x-field label="Secondary / Trade Name" name="trade_name" hint="Optional trade name, DBA or division title displayed beneath legal company name.">
                                <x-input name="trade_name" type="text" wire:model.live="trade_name" placeholder="e.g. Acme Tech Solutions"/>
                            </x-field>

                            <x-field label="Contact Line" name="contact_info" hint="Optional phone, email or website printed below GSTIN and address.">
                                <x-input name="contact_info" type="text" wire:model.live="contact_info" placeholder="e.g. Phone: +91 22 1234 5678 · Email: sales@example.com"/>
                            </x-field>

                            <div x-data="{
                                handleFile(e) {
                                    const file = e.target.files[0];
                                    if (!file) return;
                                    const reader = new FileReader();
                                    reader.onload = (event) => {
                                        @this.set('logo_data', event.target.result);
                                    };
                                    reader.readAsDataURL(file);
                                }
                            }">
                                <x-field label="Company Logo (PNG / JPEG / SVG Data URI)" name="logo_data" hint="Select an image file or paste an image data URI. Maximum dimensions: 140pt × 40pt.">
                                    <div class="flex items-center gap-3">
                                        <input type="file" accept="image/*" @change="handleFile" class="block w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100 cursor-pointer">
                                        @if ($logo_data)
                                            <button type="button" wire:click="removeLogo" class="shrink-0 text-xs text-red-600 hover:text-red-700 hover:underline">Remove logo</button>
                                        @endif
                                    </div>
                                </x-field>
                            </div>
                        @endif
                    </div>
                </x-card>

                {{-- Bank Details Card --}}
                <x-card>
                    <h2 class="mb-4 text-base font-semibold text-slate-900 border-b border-slate-100 pb-2">Bank Account Remittance Details</h2>
                    
                    <div class="space-y-4">
                        <label class="flex items-start gap-3 rounded-md border border-slate-200 bg-slate-50/50 p-3 hover:bg-slate-50 cursor-pointer">
                            <input type="checkbox" wire:model.live="show_bank_details" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            <div class="text-xs">
                                <span class="font-medium text-slate-800">Print Bank Details on Invoices</span>
                                <p class="text-slate-500 mt-0.5">Prints bank account number, IFSC, and branch details beside the UPI QR code for direct NEFT/RTGS/IMPS settlement.</p>
                            </div>
                        </label>

                        @if ($show_bank_details)
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-field label="Bank Name" name="bank_name">
                                    <x-input name="bank_name" type="text" wire:model.live="bank_name" placeholder="e.g. HDFC Bank"/>
                                </x-field>
                                <x-field label="Account Number" name="bank_account_no">
                                    <x-input name="bank_account_no" type="text" wire:model.live="bank_account_no" placeholder="e.g. 50200012345678"/>
                                </x-field>
                                <x-field label="IFSC Code" name="bank_ifsc">
                                    <x-input name="bank_ifsc" type="text" wire:model.live="bank_ifsc" placeholder="e.g. HDFC0000123" class="uppercase"/>
                                </x-field>
                                <x-field label="Branch / City" name="bank_branch">
                                    <x-input name="bank_branch" type="text" wire:model.live="bank_branch" placeholder="e.g. Fort, Mumbai"/>
                                </x-field>
                            </div>
                        @endif
                    </div>
                </x-card>

                {{-- Terms & Signatory Card --}}
                <x-card>
                    <h2 class="mb-4 text-base font-semibold text-slate-900 border-b border-slate-100 pb-2">Terms, Conditions & Signatory</h2>
                    
                    <div class="space-y-4">
                        <x-textarea
                            name="terms"
                            label="Terms & Conditions (Footer Note)"
                            rows="3"
                            wire:model.live="terms"
                            placeholder="e.g. 1. Payment due within 30 days of invoice.&#10;2. Subject to Mumbai jurisdiction.&#10;3. Goods once sold will not be taken back."
                            hint="Standard terms printed in the footer declaration area of invoices and receipts."
                        />

                        <x-field label="Signatory Designation Line" name="signature_label" hint="Default is 'Authorised signatory'. You may set 'Director', 'Partner', 'Proprietor', etc.">
                            <x-input name="signature_label" type="text" wire:model.live="signature_label" placeholder="Authorised signatory"/>
                        </x-field>
                    </div>
                </x-card>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <x-button type="submit" variant="primary">Save PDF Settings</x-button>
                </div>
            </div>

            {{-- Right column: Interactive Preview --}}
            <div class="space-y-4 lg:col-span-5">
                <x-card class="sticky top-20 border-slate-300 bg-white shadow-md">
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-brand-700">Live Invoice PDF Preview</span>
                        <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">A4 Document Simulation</span>
                    </div>

                    <div class="space-y-4 pt-2 font-sans text-xs text-slate-800">
                        {{-- Header Preview --}}
                        <div class="rounded border border-dashed border-slate-200 p-3 bg-slate-50/50">
                            @if ($hide_header)
                                <div class="flex items-center justify-center py-6 text-center text-slate-400">
                                    <span>[ 28mm Space Reserved for Pre-Printed Letterhead Stationery ]</span>
                                </div>
                            @else
                                @if ($logo_data)
                                    <div class="mb-2">
                                        <img src="{{ $logo_data }}" class="max-h-10 max-w-[140px] object-contain" alt="Preview Logo">
                                    </div>
                                @endif
                                <div class="font-bold text-sm text-slate-900">{{ $company->name }}</div>
                                @if ($trade_name)
                                    <div class="italic text-slate-600 text-[11px]">{{ $trade_name }}</div>
                                @endif
                                <div class="text-[11px] text-slate-600">{{ $company->address ?: 'Company Address, State, PIN' }}</div>
                                <div class="text-[11px] text-slate-700 font-mono">GSTIN: {{ $company->gstin ?: '27AAAAA0000A1Z5' }} · State: {{ $company->state_code }}</div>
                                @if ($contact_info)
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ $contact_info }}</div>
                                @endif
                            @endif
                        </div>

                        {{-- Middle Document Body Sample --}}
                        <div class="rounded border border-slate-200 p-2.5 text-slate-600 space-y-1.5 text-[11px]">
                            <div class="flex justify-between font-bold text-slate-800 border-b border-slate-100 pb-1">
                                <span>TAX INVOICE · SI/1</span>
                                <span>Total: ₹ 11,800.00</span>
                            </div>
                            <div class="text-[10px] text-slate-400">Statutory goods / service lines, GST tax categories, HSN/SAC, rates, and values remain unchanged and legally compliant.</div>
                        </div>

                        {{-- Bank Details Preview --}}
                        @if ($show_bank_details && ($bank_name || $bank_account_no))
                            <div class="rounded border border-slate-200 bg-slate-50/80 p-2 text-[11px]">
                                <span class="font-semibold text-slate-800 block mb-0.5">Bank Remittance Details:</span>
                                @if ($bank_name)<div>Bank: {{ $bank_name }}</div>@endif
                                @if ($bank_account_no)<div>A/C: <span class="font-mono font-medium">{{ $bank_account_no }}</span></div>@endif
                                @if ($bank_ifsc)<div>IFSC: <span class="font-mono font-medium">{{ strtoupper($bank_ifsc) }}</span></div>@endif
                                @if ($bank_branch)<div>Branch: {{ $bank_branch }}</div>@endif
                            </div>
                        @endif

                        {{-- Terms Preview --}}
                        @if ($terms)
                            <div class="rounded border border-slate-200 p-2 text-[10px] text-slate-600 whitespace-pre-line bg-slate-50/40">
                                <span class="font-semibold text-slate-800 block mb-0.5">Terms & Conditions:</span>
                                {{ $terms }}
                            </div>
                        @endif

                        {{-- Signature Preview --}}
                        <div class="pt-2 text-right">
                            <div class="text-[11px] text-slate-600">For <strong>{{ $company->name }}</strong></div>
                            <div class="mt-4 text-[10px] text-slate-500 border-t border-slate-200 inline-block pt-1 px-4">
                                {{ $signature_label ?: 'Authorised signatory' }}
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
</div>
