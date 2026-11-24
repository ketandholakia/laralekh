<?php

declare(strict_types=1);

use App\Domain\Inventory\Models\Item;
use App\Domain\Masters\Models\Company;
use App\Domain\Sales\Actions\SaveInvoiceDraft;
use App\Domain\Sales\Models\Invoice;
use App\Livewire\Inventory\ItemManager;
use App\Livewire\Sales\InvoiceEditor;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\GstSlabSeeder;
use Database\Seeders\StateSeeder;
use Livewire\Livewire;

beforeEach(function (): void {
    (new StateSeeder)->run();
});

function itemSession(string $email = 'itemadmin@example.test', array $permissions = ['items.view', 'items.manage']): array
{
    $company = companyFixture('Item Corp');
    $user = webUser($email);
    addMembership($user, $company);
    assignCompanyRole($user, $company, 'item-admin-role', $permissions);
    selectSessionCompany($user, $company, salesYear($company));

    return [$user, $company];
}

it('restricts item master to users with items.view or items.manage permission', function (): void {
    [$admin, $company] = itemSession();
    $unauth = webUser('unauth@example.test');
    addMembership($unauth, $company);
    assignCompanyRole($unauth, $company, 'viewer-role', ['invoices.view']);
    selectSessionCompany($unauth, $company, salesYear($company));

    $this->actingAs($unauth);
    $this->get('/items')->assertForbidden();

    inTenantContext($company, function (): void {
        Livewire::test(ItemManager::class)->assertForbidden();
    });

    $this->actingAs($admin);
    $this->get('/items')->assertOk()->assertSee('Item Master');
});

it('aborts item mutations for items.view-only users', function (): void {
    [$admin, $company] = itemSession();
    $viewOnly = webUser('viewonly@example.test');
    addMembership($viewOnly, $company);
    assignCompanyRole($viewOnly, $company, 'items-viewer', ['items.view']);
    selectSessionCompany($viewOnly, $company, salesYear($company));

    $this->actingAs($admin);
    $component = inTenantContext($company, fn () => Livewire::test(ItemManager::class));

    $this->actingAs($viewOnly);
    inTenantContext($company, function () use ($component): void {
        assert403(fn () => $component->instance()->openCreateModal());
        assert403(fn () => $component->instance()->saveItem());
        assert403(fn () => $component->instance()->openEditModal(1));
        assert403(fn () => $component->instance()->deleteItem(1));
    });
});

it('creates a new goods item and persists all attributes', function (): void {
    [$admin, $company] = itemSession();
    $this->actingAs($admin);

    inTenantContext($company, function () use ($company): void {
        (new GstSlabSeeder)->run();

        Livewire::test(ItemManager::class)
            ->set('sku', 'WIDGET-01')
            ->set('name', 'Mechanical Keyboard')
            ->set('hsnSac', '8471')
            ->set('unit', 'PCS')
            ->set('kind', 'goods')
            ->set('salePrice', '4500.00')
            ->set('taxRateCode', 'GST_18')
            ->set('tracksStock', true)
            ->call('saveItem')
            ->assertHasNoErrors();

        $item = Item::where('sku', 'WIDGET-01')->firstOrFail();
        expect($item->name)->toBe('Mechanical Keyboard')
            ->and($item->hsn_sac)->toBe('8471')
            ->and($item->unit)->toBe('PCS')
            ->and($item->kind)->toBe('goods')
            ->and((string) $item->sale_price)->toBe('4500.00')
            ->and($item->tax_rate_code)->toBe('GST_18')
            ->and($item->tracks_stock)->toBeTrue()
            ->and($item->company_id)->toBe($company->id);
    });
});

it('creates a service item and automatically disables stock tracking', function (): void {
    [$admin, $company] = itemSession();
    $this->actingAs($admin);

    inTenantContext($company, function (): void {
        Livewire::test(ItemManager::class)
            ->set('sku', 'CONSULT-01')
            ->set('name', 'Cloud Architecture Consulting')
            ->set('hsnSac', '998313')
            ->set('unit', 'HRS')
            ->set('kind', 'service')
            ->set('salePrice', '3500.00')
            ->set('tracksStock', true) // Should be automatically forced to false for services
            ->call('saveItem')
            ->assertHasNoErrors();

        $item = Item::where('sku', 'CONSULT-01')->firstOrFail();
        expect($item->kind)->toBe('service')
            ->and($item->tracks_stock)->toBeFalse();
    });
});

it('validates required fields, SKU regex, and HSN/SAC digit format', function (): void {
    [$admin, $company] = itemSession();
    $this->actingAs($admin);

    inTenantContext($company, function (): void {
        Livewire::test(ItemManager::class)
            ->set('sku', '')
            ->set('name', '')
            ->set('hsnSac', 'INVALID_HSN')
            ->set('salePrice', '-10.00')
            ->call('saveItem')
            ->assertHasErrors(['sku', 'name', 'hsnSac', 'salePrice']);

        Livewire::test(ItemManager::class)
            ->set('sku', 'BAD SKU WITH SPACES!')
            ->set('hsnSac', '1') // Too short (min 2 digits)
            ->call('saveItem')
            ->assertHasErrors(['sku', 'hsnSac']);
    });
});

it('enforces company-scoped SKU uniqueness', function (): void {
    [$adminA, $companyA] = itemSession('adminA@example.test');
    $companyB = companyFixture('Company B');
    $adminB = webUser('adminB@example.test');
    addMembership($adminB, $companyB);
    assignCompanyRole($adminB, $companyB, 'item-admin-b', ['items.view', 'items.manage']);
    selectSessionCompany($adminB, $companyB, salesYear($companyB));

    // Create item in Company A
    $this->actingAs($adminA);
    inTenantContext($companyA, function (): void {
        Livewire::test(ItemManager::class)
            ->set('sku', 'SHARED-SKU')
            ->set('name', 'Product in Company A')
            ->set('hsnSac', '8471')
            ->call('saveItem')
            ->assertHasNoErrors();

        // Attempting duplicate SKU in Company A must fail
        Livewire::test(ItemManager::class)
            ->set('sku', 'SHARED-SKU')
            ->set('name', 'Duplicate in Company A')
            ->set('hsnSac', '8471')
            ->call('saveItem')
            ->assertHasErrors(['sku']);
    });

    // Same SKU in Company B must succeed (strictly tenant-scoped uniqueness)
    $this->actingAs($adminB);
    inTenantContext($companyB, function (): void {
        Livewire::test(ItemManager::class)
            ->set('sku', 'SHARED-SKU')
            ->set('name', 'Product in Company B')
            ->set('hsnSac', '8471')
            ->call('saveItem')
            ->assertHasNoErrors();

        expect(Item::where('sku', 'SHARED-SKU')->count())->toBe(1);
    });
});

it('updates existing item attributes without failing own SKU uniqueness', function (): void {
    [$admin, $company] = itemSession();
    $this->actingAs($admin);

    inTenantContext($company, function (): void {
        $item = Item::create([
            'sku' => 'LAPTOP-PRO',
            'name' => '14-inch Laptop',
            'hsn_sac' => '8471',
            'unit' => 'NOS',
            'kind' => 'goods',
            'sale_price' => '65000.00',
            'tracks_stock' => true,
        ]);

        Livewire::test(ItemManager::class)
            ->call('openEditModal', $item->id)
            ->set('name', '14-inch Laptop (Upgraded 32GB RAM)')
            ->set('salePrice', '72000.00')
            ->call('saveItem')
            ->assertHasNoErrors();

        $fresh = $item->fresh();
        expect($fresh->name)->toBe('14-inch Laptop (Upgraded 32GB RAM)')
            ->and((string) $fresh->sale_price)->toBe('72000.00')
            ->and($fresh->sku)->toBe('LAPTOP-PRO');
    });
});

it('deletes an unused item', function (): void {
    [$admin, $company] = itemSession();
    $this->actingAs($admin);

    inTenantContext($company, function (): void {
        $item = Item::create([
            'sku' => 'DISCONTINUED-01',
            'name' => 'Legacy Cable',
            'hsn_sac' => '8544',
            'unit' => 'NOS',
            'kind' => 'goods',
            'sale_price' => '150.00',
        ]);

        Livewire::test(ItemManager::class)
            ->call('deleteItem', $item->id)
            ->assertHasNoErrors();

        expect(Item::where('sku', 'DISCONTINUED-01')->exists())->toBeFalse();
    });
});

it('prevents deleting an item that is referenced in an invoice draft', function (): void {
    [$admin, $company] = itemSession();
    $this->actingAs($admin);

    inTenantContext($company, function () use ($company): void {
        (new ChartOfAccountsSeeder)->run();
        [$party, $ignored, $sales] = masters();
        $rate = verifiedRate();

        $item = Item::create([
            'sku' => 'INVOICED-ITEM',
            'name' => 'Invoiced Widget',
            'hsn_sac' => '8471',
            'unit' => 'NOS',
            'kind' => 'goods',
            'sale_price' => '200.00',
        ]);

        app(SaveInvoiceDraft::class)->handle(null, draftPayload($party->id), [
            linePayload($item->id, $sales->id, $rate->id),
        ]);

        expect(Invoice::count())->toBe(1);

        Livewire::test(ItemManager::class)
            ->call('deleteItem', $item->id)
            ->assertHasErrors(['item']);

        expect(Item::where('sku', 'INVOICED-ITEM')->exists())->toBeTrue();
    });
});

it('searches and filters items by kind and stock tracking', function (): void {
    [$admin, $company] = itemSession();
    $this->actingAs($admin);

    inTenantContext($company, function (): void {
        Item::create(['sku' => 'ITEM-G-TRACK', 'name' => 'Alpha Tracked Goods', 'hsn_sac' => '1001', 'kind' => 'goods', 'tracks_stock' => true, 'sale_price' => '10.00']);
        Item::create(['sku' => 'ITEM-G-NOTRACK', 'name' => 'Beta Untracked Goods', 'hsn_sac' => '1002', 'kind' => 'goods', 'tracks_stock' => false, 'sale_price' => '20.00']);
        Item::create(['sku' => 'ITEM-SERVICE', 'name' => 'Gamma Support Service', 'hsn_sac' => '9983', 'kind' => 'service', 'tracks_stock' => false, 'sale_price' => '30.00']);

        // Search by keyword
        $component = Livewire::test(ItemManager::class)->set('search', 'Gamma');
        $items = $component->viewData('items');
        expect(count($items))->toBe(1)
            ->and($items[0]['sku'])->toBe('ITEM-SERVICE');

        // Filter by kind
        $component = Livewire::test(ItemManager::class)->set('filterKind', 'service');
        $items = $component->viewData('items');
        expect(count($items))->toBe(1)
            ->and($items[0]['sku'])->toBe('ITEM-SERVICE');

        // Filter by stock
        $component = Livewire::test(ItemManager::class)->set('filterStock', 'tracked');
        $items = $component->viewData('items');
        expect(count($items))->toBe(1)
            ->and($items[0]['sku'])->toBe('ITEM-G-TRACK');
    });
});

it('ensures invoice line lookup works seamlessly with newly created items', function (): void {
    [$admin, $company] = itemSession('admin-invoice@example.test', ['items.view', 'items.manage', 'invoices.view', 'invoices.create']);
    $this->actingAs($admin);

    inTenantContext($company, function (): void {
        (new ChartOfAccountsSeeder)->run();
        (new GstSlabSeeder)->run();
        [$party, $ignored, $sales] = masters();
        $rate = verifiedRate();

        // Create item via ItemManager
        Livewire::test(ItemManager::class)
            ->set('sku', 'POS-SCANNER-01')
            ->set('name', 'Barcode Scanner Handheld')
            ->set('hsnSac', '847190')
            ->set('unit', 'PCS')
            ->set('kind', 'goods')
            ->set('salePrice', '2800.00')
            ->set('taxRateCode', 'GST_18')
            ->call('saveItem')
            ->assertHasNoErrors();

        $item = Item::where('sku', 'POS-SCANNER-01')->firstOrFail();

        // Add line in InvoiceEditor using this new item
        $invoiceEditor = Livewire::test(InvoiceEditor::class)
            ->set('partyQuery', $party->name)
            ->call('selectParty', $party->id)
            ->set('placeOfSupply', '27')
            ->call('addItem', $item->id);

        $lines = $invoiceEditor->get('lines');
        expect(count($lines))->toBe(1)
            ->and($lines[0]['item_id'])->toBe($item->id)
            ->and($lines[0]['description'])->toBe('Barcode Scanner Handheld')
            ->and($lines[0]['hsn_sac'])->toBe('847190')
            ->and($lines[0]['unit_price'])->toBe('2800.00');
    });
});

it('keeps items strictly company-scoped', function (): void {
    [$adminA, $companyA] = itemSession('itemA@example.test');
    [$adminB, $companyB] = itemSession('itemB@example.test');

    $this->actingAs($adminA);
    inTenantContext($companyA, function (): void {
        Item::create(['sku' => 'A-EXCLUSIVE', 'name' => 'Company A Only', 'hsn_sac' => '8471', 'sale_price' => '100.00']);
    });

    $this->actingAs($adminB);
    inTenantContext($companyB, function (): void {
        expect(Item::where('sku', 'A-EXCLUSIVE')->exists())->toBeFalse();
        $items = Livewire::test(ItemManager::class)->viewData('items');
        $skus = array_column($items, 'sku');
        expect($skus)->not->toContain('A-EXCLUSIVE');
    });
});
