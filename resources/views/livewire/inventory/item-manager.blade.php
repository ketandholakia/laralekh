<div class="space-y-6">
    <x-page-header
        title="Item Master"
        eyebrow="Inventory · Catalog"
        sub="Define and maintain goods and services with HSN/SAC codes, tax rates, and unit pricing."
    >
        <x-slot name="actions">
            @can('items.manage')
                <x-button type="button" wire:click="openCreateModal" variant="primary" icon="plus">New Item</x-button>
            @endcan
        </x-slot>
    </x-page-header>

    @if (session('status'))
        <x-alert type="success">{{ session('status') }}</x-alert>
    @endif

    @error('item')
        <x-alert type="danger">{{ $message }}</x-alert>
    @enderror

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-card class="p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Items</span>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $stats['total'] }}</p>
        </x-card>
        <x-card class="p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Goods</span>
            <p class="mt-1 text-2xl font-bold text-brand-700">{{ $stats['goods'] }}</p>
        </x-card>
        <x-card class="p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Services</span>
            <p class="mt-1 text-2xl font-bold text-purple-700">{{ $stats['services'] }}</p>
        </x-card>
        <x-card class="p-4">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tracked Stock</span>
            <p class="mt-1 text-2xl font-bold text-emerald-700">{{ $stats['tracked'] }}</p>
        </x-card>
    </div>

    {{-- Filters and Catalog Table --}}
    <x-card>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-72">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by name, SKU, or HSN/SAC..."
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                    >
                </div>

                <div>
                    <select
                        wire:model.live="filterKind"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                    >
                        <option value="all">All Types</option>
                        <option value="goods">Goods Only</option>
                        <option value="service">Services Only</option>
                    </select>
                </div>

                <div>
                    <select
                        wire:model.live="filterStock"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                    >
                        <option value="all">All Stock Modes</option>
                        <option value="tracked">Tracked Stock</option>
                        <option value="untracked">Untracked</option>
                    </select>
                </div>
            </div>

            <div class="text-xs text-slate-500">
                Showing <span class="font-semibold text-slate-800">{{ count($items) }}</span> item(s)
            </div>
        </div>

        <x-table caption="Company items and inventory master">
            <x-slot name="head">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Item & SKU</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">HSN/SAC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Unit</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Tax Rate</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Sale Price</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">Stock</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                </tr>
            </x-slot>
            <x-slot name="body">
                @forelse ($items as $item)
                    <tr class="border-b border-slate-100 hover:bg-slate-50/50">
                        <td class="px-4 py-3">
                            <span class="font-medium text-slate-900">{{ $item['name'] }}</span>
                            <span class="block font-mono text-xs text-slate-500">{{ $item['sku'] }}</span>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-700">
                            {{ $item['hsn_sac'] }}
                        </td>
                        <td class="px-4 py-3">
                            <x-badge :variant="$item['kind'] === 'goods' ? 'brand' : 'neutral'" size="sm">
                                {{ ucfirst($item['kind']) }}
                            </x-badge>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-600 font-medium">
                            {{ $item['unit'] }}
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-700">
                            @if ($item['tax_rate_code'])
                                <x-badge variant="brand" size="sm">{{ $item['tax_rate_code'] }}</x-badge>
                            @else
                                <span class="text-slate-400 italic">None</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-sm font-semibold text-slate-900">
                            ₹{{ $item['sale_price'] }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($item['tracks_stock'])
                                <x-badge variant="success" size="sm">Tracked</x-badge>
                            @else
                                <x-badge variant="neutral" size="sm">No</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-sm">
                            <div class="flex items-center justify-end gap-2">
                                @can('items.manage')
                                    <button
                                        type="button"
                                        wire:click="openEditModal({{ $item['id'] }})"
                                        class="rounded px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100 hover:text-slate-900"
                                    >
                                        Edit
                                    </button>
                                    @if (! $item['is_used'])
                                        <button
                                            type="button"
                                            wire:click="deleteItem({{ $item['id'] }})"
                                            wire:confirm="Are you sure you want to delete item '{{ $item['sku'] }}'?"
                                            class="rounded px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50 hover:text-red-900"
                                        >
                                            Delete
                                        </button>
                                    @else
                                        <span class="text-[11px] text-slate-400" title="Referenced in vouchers or movements">In use</span>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-slate-400">
                            <x-icon name="package" class="mx-auto mb-2 h-8 w-8 text-slate-300" />
                            <p class="font-medium text-slate-600">No items found</p>
                            <p class="text-xs text-slate-400">Try adjusting your search query or add a new item.</p>
                        </td>
                    </tr>
                @endforelse
            </x-slot>
        </x-table>
    </x-card>

    {{-- Create / Edit Item Modal --}}
    @if ($showItemModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="w-full max-w-lg rounded-xl border border-slate-200 bg-white p-6 shadow-pop max-h-[90vh] overflow-y-auto">
                <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-base font-semibold text-slate-900">
                        {{ $editingItemId ? 'Edit Item' : 'New Item' }}
                    </h2>
                    <button type="button" wire:click="closeItemModal" class="rounded p-1 text-slate-400 hover:text-slate-600">
                        <x-icon name="close" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit.prevent="saveItem" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-field label="SKU / Item Code" name="sku" :error="$errors->first('sku')" hint="Unique identifier (e.g. WIDGET-A)">
                            <x-input name="sku" wire:model="sku" placeholder="WIDGET-01" />
                        </x-field>

                        <x-field label="HSN / SAC Code" name="hsnSac" :error="$errors->first('hsnSac')" hint="2 to 8 digits">
                            <x-input name="hsnSac" wire:model="hsnSac" placeholder="8471" />
                        </x-field>
                    </div>

                    <x-field label="Item Name" name="name" :error="$errors->first('name')">
                        <x-input name="name" wire:model="name" placeholder="Desktop Computer Monitor 24-inch" />
                    </x-field>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600">Type</label>
                            <select
                                wire:model.live="kind"
                                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                            >
                                <option value="goods">Goods</option>
                                <option value="service">Service</option>
                            </select>
                            @error('kind') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600">Unit (UQC)</label>
                            <input
                                type="text"
                                wire:model="unit"
                                list="uqc-units"
                                placeholder="NOS"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                            >
                            <datalist id="uqc-units">
                                <option value="NOS">Numbers</option>
                                <option value="PCS">Pieces</option>
                                <option value="KGS">Kilograms</option>
                                <option value="MTR">Meters</option>
                                <option value="BOX">Boxes</option>
                                <option value="SET">Sets</option>
                                <option value="LTR">Liters</option>
                                <option value="HRS">Hours</option>
                                <option value="BAG">Bags</option>
                                <option value="OTH">Others</option>
                            </datalist>
                            @error('unit') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <x-field label="Sale Price (₹)" name="salePrice" :error="$errors->first('salePrice')">
                            <x-input name="salePrice" wire:model="salePrice" placeholder="0.00" />
                        </x-field>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-600">Default Tax Rate</label>
                            <select
                                wire:model="taxRateCode"
                                class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 focus:border-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-600/20"
                            >
                                <option value="">No Default Rate</option>
                                @foreach ($taxRates as $rate)
                                    <option value="{{ $rate['code'] }}">{{ $rate['label'] }}</option>
                                @endforeach
                            </select>
                            @error('taxRateCode') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex flex-col justify-center pt-5">
                            <label class="flex items-center gap-2 cursor-pointer {{ $kind === 'service' ? 'opacity-50 cursor-not-allowed' : '' }}">
                                <input
                                    type="checkbox"
                                    wire:model="tracksStock"
                                    {{ $kind === 'service' ? 'disabled' : '' }}
                                    class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                >
                                <span class="text-sm font-medium text-slate-700">Track Inventory Stock</span>
                            </label>
                            @if ($kind === 'service')
                                <p class="mt-1 text-[11px] text-slate-400">Stock tracking is disabled for services.</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 border-t border-slate-100 pt-4">
                        <x-button type="button" wire:click="closeItemModal" variant="secondary">Cancel</x-button>
                        <x-button type="submit" variant="primary">
                            {{ $editingItemId ? 'Update Item' : 'Create Item' }}
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
