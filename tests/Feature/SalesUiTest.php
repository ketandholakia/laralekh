<?php

use App\Domain\Accounting\Models\NumberSequence;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Sales\Actions\PostInvoice;
use App\Domain\Sales\Actions\SaveInvoiceDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\InvoiceItem;
use App\Domain\Shared\Models\Activity;
use App\Livewire\Sales\InvoiceEditor;
use App\Livewire\Sales\InvoiceList;
use App\Livewire\Sales\InvoiceView;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('lists invoices only for the session company', function () {
    [$user, $company] = webSession();
    $other = companyFixture('Other');
    $this->actingAs($user);
    inTenantContext($company, fn (): bool => Invoice::query()->get()->isEmpty());
    // Create an invoice in the other company; it must never leak into this session.
    inTenantContext($other, function (): void {
        (new ChartOfAccountsSeeder)->run();
        FinancialYear::firstOrCreate(['name' => '2026-27'], ['starts_on' => '2026-04-01', 'ends_on' => '2027-03-31']);
        [$party, $item, $sales] = masters();
        Invoice::create(['financial_year_id' => FinancialYear::sole()->id, 'party_id' => $party->id,
            'date' => '2026-11-05', 'place_of_supply' => '27'])
            ->items()->create(['financial_year_id' => FinancialYear::sole()->id, 'item_id' => $item->id,
                'sales_ledger_id' => $sales->id, 'position' => 1, 'description' => 'Secret item',
                'hsn_sac' => '8471', 'quantity' => '1', 'unit_price' => '999.00', 'discount' => '0.00']);
    });
    inTenantContext($company, function (): void {
        Livewire::test(InvoiceList::class)->assertSee('No invoices');
        expect(Invoice::count())->toBe(0);
    });
});

it('saves a draft through the editor without allocating a number', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        Livewire::test(InvoiceEditor::class)
            ->set('partyQuery', 'Customer')->call('selectParty', $party->id)
            ->set('date', '2026-11-05')->set('placeOfSupply', '27')
            ->call('addItem', $item->id)
            ->set('lines.0.quantity', '2')
            ->set('lines.0.tax_rate_id', $rate->id)
            ->set('lines.0.sales_ledger_id', $sales->id)
            ->call('save');
        $invoice = Invoice::sole();
        expect($invoice->status)->toBe('draft')->and($invoice->number)->toBeNull()
            ->and(NumberSequence::count())->toBe(0)
            ->and(InvoiceItem::sole()->quantity)->toBe('2.000')
            ->and(Voucher::count())->toBe(0);
    });
});

it('rejects draft lines whose rate is not verified and effective on the invoice date', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate('FUTURE18');
        $rate->update(['effective_from' => '2027-01-01']);
        Livewire::test(InvoiceEditor::class)
            ->set('partyQuery', 'Customer')->call('selectParty', $party->id)
            ->set('date', '2026-11-05')->set('placeOfSupply', '27')
            ->call('addItem', $item->id)
            ->set('lines.0.tax_rate_id', $rate->id)
            ->set('lines.0.sales_ledger_id', $sales->id)
            ->call('save')
            ->assertHasErrors('lines');
        expect(Invoice::count())->toBe(0);
    });
});

it('previews live totals through the same calculator the poster uses', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        Livewire::test(InvoiceEditor::class)
            ->set('date', '2026-11-05')->set('placeOfSupply', '24') // inter-state
            ->call('addItem', $item->id)
            ->set('lines.0.quantity', '3')
            ->set('lines.0.unit_price', '100.05')
            ->set('lines.0.tax_rate_id', $rate->id)
            ->set('lines.0.sales_ledger_id', $sales->id)
            ->tap(function ($component): void {
                expect($component->preview['totals']['taxable'])->toBe('300.15')
                    ->and($component->preview['totals']['igst'])->toBe('54.03')
                    ->and($component->preview['totals']['total'])->toBe('354.18');
            });
    });
});

it('posts through the editor once; duplicate submissions fail and use no extra number', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        $component = Livewire::test(InvoiceEditor::class)
            ->set('partyQuery', 'Customer')->call('selectParty', $party->id)
            ->set('date', '2026-11-05')->set('placeOfSupply', '27')
            ->call('addItem', $item->id)
            ->set('lines.0.unit_price', '1000.00')
            ->set('lines.0.tax_rate_id', $rate->id)
            ->set('lines.0.sales_ledger_id', $sales->id)
            ->call('post');
        $invoice = Invoice::sole();
        expect($invoice->status)->toBe('posted')->and($invoice->number)->toBe(1)
            ->and(Voucher::count())->toBe(1)->and(NumberSequence::sole()->last_number)->toBe(1)
            ->and(Activity::count())->toBe(1);
        // A second submission of the same invoice fails inside the posting
        // transaction and consumes no number.
        expect(fn () => app(PostInvoice::class)->handle($invoice->id))->toThrow(DomainException::class);
        expect(NumberSequence::sole()->last_number)->toBe(1)->and(Voucher::count())->toBe(1);
    });
});

it('treats posted invoices as read-only in the editor and view', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    $invoice = inTenantContext($company, function (): Invoice {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        $draft = app(SaveInvoiceDraft::class)->handle(null, draftPayload($party->id), [linePayload($item->id, $sales->id, $rate->id)]);

        return app(PostInvoice::class)->handle($draft->id)->fresh(['items', 'party', 'voucher']);
    });
    inTenantContext($company, function () use ($invoice): void {
        Livewire::test(InvoiceView::class, ['invoiceId' => $invoice->id])
            ->assertOk()->assertSee('Invoice SI/1');
        // Reopening the posted invoice in the editor redirects to the read-only view.
        Livewire::test(InvoiceEditor::class, ['invoice' => $invoice->id]);
        // And the draft action refuses to overwrite it.
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        expect(fn () => app(SaveInvoiceDraft::class)->handle(
            $invoice->id, draftPayload($party->id, ['date' => '2026-11-06']),
            [linePayload($item->id, $sales->id, $rate->id)],
        ))->toThrow(ValidationException::class);
        expect($invoice->fresh()->date->format('Y-m-d'))->toBe('2026-11-05');
    });
});

it('refuses posting for users without the invoices.post permission', function () {
    [$user, $company] = webSession('clerk', CLERK_PERMISSIONS);
    $this->actingAs($user);
    $draft = inTenantContext($company, function (): Invoice {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();

        return app(SaveInvoiceDraft::class)->handle(null, draftPayload($party->id), [linePayload($item->id, $sales->id, $rate->id)]);
    });
    inTenantContext($company, function () use ($user, $draft): void {
        expect($user->can('invoices.create'))->toBeTrue()->and($user->can('invoices.post'))->toBeFalse();
        $component = Livewire::test(InvoiceEditor::class, ['invoice' => $draft->id]);
        assert403(fn () => $component->instance()->post());
        expect(Invoice::sole()->status)->toBe('draft')->and(Voucher::count())->toBe(0);
    });
});

it('validates draft line quantity, discount and date bounds', function ($lineOverrides, $invoiceOverrides, $message) {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function () use ($lineOverrides, $invoiceOverrides, $message): void {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        $component = Livewire::test(InvoiceEditor::class)
            ->set('partyQuery', 'Customer')->call('selectParty', $party->id)
            ->set('date', $invoiceOverrides['date'])
            ->set('placeOfSupply', '27')
            ->call('addItem', $item->id)
            ->set('lines.0.tax_rate_id', $rate->id)
            ->set('lines.0.sales_ledger_id', $sales->id);
        foreach ($lineOverrides as $field => $value) {
            $component = $component->set('lines.0.'.$field, $value);
        }
        $component->call('save')->assertHasErrors('lines');
        expect(Invoice::count())->toBe(0)->and($message)->toBeString();
    });
})->with([
    'bad quantity' => [['quantity' => '0'], ['date' => '2026-11-05'], 'Quantity must be positive'],
    'fractional quantity too deep' => [['quantity' => '1.0005'], ['date' => '2026-11-05'], 'three decimals'],
    'negative price' => [['unit_price' => '-5.00'], ['date' => '2026-11-05'], 'decimal string'],
    'discount above line value' => [['discount' => '2000.00'], ['date' => '2026-11-05'], 'exceeds line'],
    'date outside financial year' => [[], ['date' => '2027-04-02'], 'financial year'],
]);

it('rejects a verified rate that has lapsed before the invoice date', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate('OLD12', '12');
        $rate->update(['effective_to' => '2026-06-30']);
        Livewire::test(InvoiceEditor::class)
            ->set('partyQuery', 'Customer')->call('selectParty', $party->id)
            ->set('date', '2026-11-05')->set('placeOfSupply', '27')
            ->call('addItem', $item->id)
            ->set('lines.0.tax_rate_id', $rate->id)
            ->set('lines.0.sales_ledger_id', $sales->id)
            ->call('save')
            ->assertHasErrors('lines');
        expect(Invoice::count())->toBe(0);
    });
});

it('changes the rate dropdown to only verified rates effective on the chosen date', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $current = verifiedRate('NOW18', '18');
        $future = verifiedRate('NEXT5', '5');
        $future->update(['effective_from' => '2027-01-01']);
        $component = Livewire::test(InvoiceEditor::class)->set('date', '2026-11-05');
        $ids = array_column($component->rateOptions, 'id');
        expect($ids)->toContain($current->id)->not->toContain($future->id);
        // Moving the date into the future rate's window makes it selectable.
        $component->set('date', '2027-01-15');
        expect(array_column($component->rateOptions, 'id'))->toContain($future->id);
    });
});

it('never accepts a foreign company id through draft input', function () {
    [$user, $company] = webSession();
    $other = companyFixture('Other');
    $this->actingAs($user);
    inTenantContext($company, function () use ($other, $company): void {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        $invoice = app(SaveInvoiceDraft::class)->handle(
            null, draftPayload($party->id) + ['company_id' => $other->id],
            [linePayload($item->id, $sales->id, $rate->id)],
        );
        expect($invoice->company_id)->toBe($company->id);
    });
});
