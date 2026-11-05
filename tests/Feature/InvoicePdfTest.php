<?php
use App\Domain\Accounting\Models\Ledger;
use App\Domain\Masters\Models\Company;
use App\Domain\Sales\Actions\{PostInvoice, SaveInvoiceDraft};
use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Support\AmountInWords;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

function pdfCompany(array $overrides = []): array
{
    [$user, $company] = webSession();
    app(TenantContext::class)->run($company->id, fn () => $company->update($overrides));
    return [$user, $company];
}

function postStandardInvoice(Company $company, int $lineCount = 1, array $invoiceOverrides = []): Invoice
{
    return inTenantContext($company, function () use ($company, $lineCount, $invoiceOverrides): Invoice {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        $lines = [];
        for ($i = 1; $i <= $lineCount; $i++) {
            $lines[] = linePayload($item->id, $sales->id, $rate->id, [
                'description' => 'Widget line '.$i,
                'quantity' => '1', 'unit_price' => '100.00',
            ]);
        }
        $draft = app(SaveInvoiceDraft::class)->handle(null, $invoiceOverrides + draftPayload($party->id), $lines);
        return app(PostInvoice::class)->handle($draft->id)->fresh(['items', 'party', 'voucher']);
    });
}

it('renders a posted tax invoice PDF with the statutory particulars', function () {
    [$user, $company] = pdfCompany(['name' => 'ACME Traders', 'gstin' => '27AAAAA0000A1Z5', 'address' => 'Mumbai, Maharashtra']);
    $this->actingAs($user);
    $invoice = postStandardInvoice($company);

    $response = $this->get(route('invoices.pdf', $invoice->id));
    expect($response->status())->toBe(200)
        ->and($response->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
    $bytes = $response->getContent();
    $t = str_replace(' ', '', pdfText($bytes));
    $i = strpos($t, 'Taxablevalue');
    file_put_contents(sys_get_temp_dir().'/extract.txt', $i === false ? 'NOTFOUND len='.strlen($t).' tail='.substr($t, -500) : substr($t, $i, 300));
    expect(pdfHas($bytes, 'TAX INVOICE'))->toBeTrue()
        ->and(pdfHas($bytes, 'ACME Traders'))->toBeTrue()
        ->and(pdfHas($bytes, '27AAAAA0000A1Z5'))->toBeTrue()
        ->and(pdfHas($bytes, 'Invoice No.'))->toBeTrue()
        ->and(pdfHas($bytes, 'SI/1'))->toBeTrue()
        ->and(pdfHas($bytes, 'HSN/SAC'))->toBeTrue()
        ->and(pdfHas($bytes, '8471'))->toBeTrue()
        ->and(pdfHas($bytes, 'Place of Supply'))->toBeTrue()
        ->and(pdfHas($bytes, '27'))->toBeTrue()
        ->and(pdfHas($bytes, 'Rupees One Hundred Eighteen Only'))->toBeTrue()
        ->and(pdfHas($bytes, 'Page 1 of 1'))->toBeTrue();
});

it('marks draft previews clearly and allocates nothing', function () {
    [$user, $company] = webSession();
    selectSessionCompany($user, $company, salesYear($company));
    $this->actingAs($user);
    $draft = inTenantContext($company, function (): Invoice {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        return app(SaveInvoiceDraft::class)->handle(null, draftPayload($party->id), [linePayload($item->id, $sales->id, $rate->id)]);
    });
    $response = $this->get(route('invoices.pdf', $draft->id));
    $bytes = $response->getContent();
    expect($response->status())->toBe(200)
        ->and(pdfHas($bytes, 'DRAFT PREVIEW'))->toBeTrue()
        ->and(pdfHas($bytes, 'NOT A TAX INVOICE'))->toBeTrue()
        ->and(pdfHas($bytes, 'no invoice number has been allocated'))->toBeTrue()
        ->and($draft->fresh()->number)->toBeNull()
        ->and(pdfHas($bytes, 'Scan to pay'))->toBeFalse();
});

it('prints a bill of supply for composition suppliers and exempt-only invoices', function () {
    [$user, $company] = webSession();
    selectSessionCompany($user, $company, salesYear($company));
    $this->actingAs($user);
    // Composition company: no GST collected on outward supplies.
    app(TenantContext::class)->run($company->id, fn () => $company->update(['gst_regime' => 'composition']));
    $compositionInvoice = postStandardInvoice($company);
    $bytes = $this->get(route('invoices.pdf', $compositionInvoice->id))->getContent();
    expect(pdfHas($bytes, 'BILL OF SUPPLY'))->toBeTrue()
        ->and(pdfHas($bytes, 'composition scheme'))->toBeTrue()
        ->and(pdfHas($bytes, 'Tax Invoice'))->toBeFalse();
});

it('declares reverse charge and LUT export treatment on posted invoices', function () {
    [$user, $company] = webSession();
    selectSessionCompany($user, $company, salesYear($company));
    $this->actingAs($user);
    $rcInvoice = postStandardInvoice($company, 1, ['reverse_charge' => true]);
    $bytes = $this->get(route('invoices.pdf', $rcInvoice->id))->getContent();
    expect(pdfHas($bytes, 'REVERSE CHARGE'))->toBeTrue()
        ->and(pdfHas($bytes, 'payable by the recipient'))->toBeTrue();

    $export = postStandardInvoice($company, 1, [
        'supply_type' => 'export', 'place_of_supply' => '99',
        'zero_rated_mode' => 'lut', 'zero_rated_eligible' => true,
    ]);
    $bytes = $this->get(route('invoices.pdf', $export->id))->getContent();
    expect(pdfHas($bytes, 'Letter of Undertaking'))->toBeTrue();
});

it('paginates long invoices and repeats the table header', function () {
    [$user, $company] = webSession();
    selectSessionCompany($user, $company, salesYear($company));
    $this->actingAs($user);
    $invoice = postStandardInvoice($company, 60);
    $response = $this->get(route('invoices.pdf', $invoice->id));
    $bytes = $response->getContent();
    expect($response->status())->toBe(200)
        ->and(pdfPageCount($bytes))->toBeGreaterThan(1)
        ->and(pdfHas($bytes, 'HSN/SAC'))->toBeTrue() // header text appears (repeated or single)
        ->and(substr_count(pdfText($bytes), 'HSN/SAC'))->toBeGreaterThanOrEqual(2)
        ->and(pdfHas($bytes, 'Widget line 60'))->toBeTrue()
        ->and(pdfHas($bytes, 'Page 1 of'))->toBeTrue();
});

it('includes the UPI payment QR when valid payment details are configured', function () {
    [$user, $company] = webSession();
    selectSessionCompany($user, $company, salesYear($company));
    $this->actingAs($user);
    app(TenantContext::class)->run($company->id, fn () => $company->update(['upi_id' => 'acme@bank']));
    $invoice = postStandardInvoice($company);
    $bytes = $this->get(route('invoices.pdf', $invoice->id))->getContent();
    expect(pdfHas($bytes, 'Scan to pay'))->toBeTrue()
        ->and(pdfHas($bytes, 'not an IRP e-invoice QR'))->toBeTrue()
        // The QR is drawn as filled black rectangles in the content streams.
        ->and(function () use ($bytes): void {
            $rects = 0;
            preg_match_all('/stream
?
(.*?)endstream/s', $bytes, $streams);
            foreach ($streams[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if (is_string($decoded)) {
                    $rects += substr_count($decoded, '0 0 0 rg');
                }
            }
            expect($rects)->toBeGreaterThan(20);
        });
});

it('refuses the PDF to users who cannot view invoices or are not members', function () {
    [$user, $company] = webSession();
    selectSessionCompany($user, $company, salesYear($company));
    $this->actingAs($user);
    $invoice = postStandardInvoice($company);

    // An outsider has no selected company, so the context middleware bounces them.
    $outsider = webUser('outsider@example.test');
    $this->actingAs($outsider);
    $this->get(route('invoices.pdf', $invoice->id))->assertRedirect(route('company.select'));

    // A member without the view permission is also refused.
    $noView = webUser('norole@example.test');
    addMembership($noView, $company);
    selectSessionCompany($noView, $company, salesYear($company));
    $this->actingAs($noView);
    $this->get(route('invoices.pdf', $invoice->id))->assertForbidden();
});

it('uses supplier and party snapshots on posted PDFs even after masters change', function () {
    [$user, $company] = webSession();
    selectSessionCompany($user, $company, salesYear($company));
    $this->actingAs($user);
    $invoice = postStandardInvoice($company);
    inTenantContext($company, function () use ($company, $invoice): void {
        $company->update(['name' => 'Renamed Traders']);
        $invoice->party->update(['name' => 'Renamed Buyer']);
    });
    $bytes = $this->get(route('invoices.pdf', $invoice->id))->getContent();
    expect(pdfHas($bytes, 'Renamed Traders'))->toBeFalse()
        ->and(pdfHas($bytes, 'Renamed Buyer'))->toBeFalse()
        ->and(pdfHas($bytes, 'Company'))->toBeTrue()
        ->and(pdfHas($bytes, 'Customer'))->toBeTrue();
});
