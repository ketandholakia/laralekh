<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Domain\Gst\Models\TaxRate;
use App\Domain\Inventory\Models\Item;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class ItemManager extends Component
{
    public string $search = '';

    public string $filterKind = 'all';

    public string $filterStock = 'all';

    public bool $showItemModal = false;

    public ?int $editingItemId = null;

    // Form inputs
    public string $sku = '';

    public string $name = '';

    public string $hsnSac = '';

    public string $unit = 'NOS';

    public string $kind = 'goods';

    public string $salePrice = '0.00';

    public string $taxRateCode = '';

    public bool $tracksStock = false;

    public function mount(): void
    {
        $this->guardView();
    }

    private function companyId(): int
    {
        return app(TenantContext::class)->hasContext()
            ? app(TenantContext::class)->id()
            : (app(CompanySession::class)->companyId() ?? abort(403, 'A company context is required.'));
    }

    private function inCompanyScope(Closure $callback): mixed
    {
        $companyId = $this->companyId();
        if (app(TenantContext::class)->hasContext() && app(TenantContext::class)->id() === $companyId) {
            return $callback($companyId);
        }

        return app(TenantContext::class)->run($companyId, fn () => $callback($companyId));
    }

    private function guardView(): void
    {
        $user = auth()->user();
        $companyId = $this->companyId();
        abort_unless($user && $user->isMemberOf($companyId), 403);
        abort_unless($this->inCompanyScope(fn () => $user->can('items.view') || $user->can('items.manage')), 403);
    }

    private function guardManage(): void
    {
        $user = auth()->user();
        $companyId = $this->companyId();
        abort_unless($user && $user->isMemberOf($companyId), 403);
        abort_unless($this->inCompanyScope(fn () => $user->can('items.manage')), 403);
    }

    public function getItemsProperty(): array
    {
        return $this->inCompanyScope(function (): array {
            $query = Item::query();

            if ($this->search !== '') {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhere('hsn_sac', 'like', $term);
                });
            }

            if ($this->filterKind === 'goods' || $this->filterKind === 'service') {
                $query->where('kind', $this->filterKind);
            }

            if ($this->filterStock === 'tracked') {
                $query->where('tracks_stock', true);
            } elseif ($this->filterStock === 'untracked') {
                $query->where('tracks_stock', false);
            }

            return $query->orderBy('name')->get()->map(function (Item $item): array {
                return [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'hsn_sac' => $item->hsn_sac,
                    'unit' => $item->unit,
                    'kind' => $item->kind,
                    'tracks_stock' => (bool) $item->tracks_stock,
                    'sale_price' => (string) $item->sale_price,
                    'tax_rate_code' => $item->tax_rate_code,
                    'is_used' => $item->isUsed(),
                ];
            })->all();
        });
    }

    public function getStatsProperty(): array
    {
        return $this->inCompanyScope(function (): array {
            $total = Item::count();
            $goods = Item::where('kind', 'goods')->count();
            $services = Item::where('kind', 'service')->count();
            $tracked = Item::where('tracks_stock', true)->count();

            return [
                'total' => $total,
                'goods' => $goods,
                'services' => $services,
                'tracked' => $tracked,
            ];
        });
    }

    public function getTaxRateOptionsProperty(): array
    {
        return $this->inCompanyScope(function (): array {
            $rates = TaxRate::query()
                ->select(['code', 'rate'])
                ->distinct()
                ->orderBy('rate')
                ->get()
                ->map(fn (TaxRate $r) => [
                    'code' => $r->code,
                    'label' => $r->code.' ('.((string) $r->rate).'%)',
                ])
                ->all();

            if ($rates !== []) {
                return $rates;
            }

            // Fallback GST slabs if no rates seeded in company
            return [
                ['code' => 'GST_0', 'label' => 'GST_0 (0%)'],
                ['code' => 'GST_5', 'label' => 'GST_5 (5%)'],
                ['code' => 'GST_12', 'label' => 'GST_12 (12%)'],
                ['code' => 'GST_18', 'label' => 'GST_18 (18%)'],
                ['code' => 'GST_28', 'label' => 'GST_28 (28%)'],
                ['code' => 'GST_40', 'label' => 'GST_40 (40%)'],
            ];
        });
    }

    public function openCreateModal(): void
    {
        $this->guardManage();
        $this->reset([
            'sku', 'name', 'hsnSac', 'unit', 'kind', 'salePrice', 'taxRateCode', 'tracksStock', 'editingItemId',
        ]);
        $this->unit = 'NOS';
        $this->kind = 'goods';
        $this->salePrice = '0.00';
        $this->tracksStock = false;
        $this->resetErrorBag();
        $this->showItemModal = true;
    }

    public function openEditModal(int $itemId): void
    {
        $this->guardManage();
        $this->inCompanyScope(function () use ($itemId): void {
            $item = Item::findOrFail($itemId);
            $this->editingItemId = $item->id;
            $this->sku = $item->sku;
            $this->name = $item->name;
            $this->hsnSac = $item->hsn_sac;
            $this->unit = $item->unit;
            $this->kind = $item->kind;
            $this->salePrice = (string) $item->sale_price;
            $this->taxRateCode = $item->tax_rate_code ?? '';
            $this->tracksStock = (bool) $item->tracks_stock;
            $this->resetErrorBag();
            $this->showItemModal = true;
        });
    }

    public function closeItemModal(): void
    {
        $this->showItemModal = false;
        $this->reset([
            'sku', 'name', 'hsnSac', 'unit', 'kind', 'salePrice', 'taxRateCode', 'tracksStock', 'editingItemId',
        ]);
        $this->resetErrorBag();
    }

    public function saveItem(): void
    {
        $this->guardManage();

        $companyId = $this->companyId();

        $this->validate([
            'sku' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9_\-\.\/]+$/',
                Rule::unique('items', 'sku')
                    ->where('company_id', $companyId)
                    ->ignore($this->editingItemId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'hsnSac' => ['required', 'string', 'regex:/^[0-9]{2,8}$/'],
            'unit' => ['required', 'string', 'max:10'],
            'kind' => ['required', 'in:goods,service'],
            'salePrice' => ['required', 'numeric', 'min:0'],
            'taxRateCode' => ['nullable', 'string', 'max:40'],
            'tracksStock' => ['boolean'],
        ], [
            'sku.regex' => 'The SKU may only contain letters, numbers, dashes, underscores, dots, and slashes.',
            'hsnSac.regex' => 'The HSN/SAC code must be between 2 and 8 digits.',
        ]);

        $cleanSku = strtoupper(trim($this->sku));
        $cleanName = trim($this->name);
        $cleanHsn = trim($this->hsnSac);
        $cleanUnit = strtoupper(trim($this->unit));
        $cleanKind = $this->kind;
        $cleanTaxCode = trim($this->taxRateCode) !== '' ? trim($this->taxRateCode) : null;
        $cleanTracksStock = $cleanKind === 'service' ? false : (bool) $this->tracksStock;
        $cleanPrice = number_format((float) $this->salePrice, 2, '.', '');

        $this->inCompanyScope(function () use (
            $cleanSku, $cleanName, $cleanHsn, $cleanUnit, $cleanKind, $cleanTaxCode, $cleanTracksStock, $cleanPrice
        ): void {
            if ($this->editingItemId !== null) {
                $item = Item::findOrFail($this->editingItemId);
                $item->update([
                    'sku' => $cleanSku,
                    'name' => $cleanName,
                    'hsn_sac' => $cleanHsn,
                    'unit' => $cleanUnit,
                    'kind' => $cleanKind,
                    'sale_price' => $cleanPrice,
                    'tax_rate_code' => $cleanTaxCode,
                    'tracks_stock' => $cleanTracksStock,
                ]);
                session()->flash('status', 'Item "'.$cleanSku.'" updated successfully.');
            } else {
                Item::create([
                    'sku' => $cleanSku,
                    'name' => $cleanName,
                    'hsn_sac' => $cleanHsn,
                    'unit' => $cleanUnit,
                    'kind' => $cleanKind,
                    'sale_price' => $cleanPrice,
                    'tax_rate_code' => $cleanTaxCode,
                    'tracks_stock' => $cleanTracksStock,
                ]);
                session()->flash('status', 'Item "'.$cleanSku.'" created successfully.');
            }

            $this->closeItemModal();
        });
    }

    public function deleteItem(int $itemId): void
    {
        $this->guardManage();

        $this->inCompanyScope(function () use ($itemId): void {
            $item = Item::findOrFail($itemId);

            if ($item->isUsed()) {
                $this->addError('item', 'Cannot delete item "'.$item->sku.'" because it is referenced in invoices, purchase bills, or stock movements.');

                return;
            }

            $sku = $item->sku;
            $item->delete();
            session()->flash('status', 'Item "'.$sku.'" deleted successfully.');
        });
    }

    public function render(): View
    {
        return view('livewire.inventory.item-manager', [
            'items' => $this->items,
            'stats' => $this->stats,
            'taxRates' => $this->taxRateOptions,
        ]);
    }
}
