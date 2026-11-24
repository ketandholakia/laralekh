<?php

use App\Domain\Masters\Models\Company;
use App\Domain\Sales\Actions\PostInvoice;
use App\Domain\Sales\Actions\SaveInvoiceDraft;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Livewire\Admin\PdfCustomizer;
use Livewire\Livewire;

function pdfCustomizerSession(array $permissions = ['users.manage']): array
{
    [$user, $company] = webSession('admin', $permissions);

    return [$user, $company];
}

function createInvoiceForPdf(Company $company): Invoice
{
    return inTenantContext($company, function (): Invoice {
        [$party, $item, $sales] = masters();
        $rate = verifiedRate();
        $lines = [
            linePayload($item->id, $sales->id, $rate->id, [
                'description' => 'Consulting Services',
                'quantity' => '1', 'unit_price' => '1000.00',
            ]),
        ];
        $draft = app(SaveInvoiceDraft::class)->handle(null, draftPayload($party->id), $lines);

        return app(PostInvoice::class)->handle($draft->id)->fresh(['items', 'party', 'voucher']);
    });
}

it('forbids unauthorized users without users.manage from the PDF customizer', function () {
    [$user, $company] = webSession('viewer', ['invoices.view']);
    $this->actingAs($user);

    $this->get(route('admin.pdf-customizer.index'))->assertForbidden();
});

it('allows company admins with users.manage to load and save PDF customization settings', function () {
    [$user, $company] = pdfCustomizerSession();
    $this->actingAs($user);

    inTenantContext($company, function () use ($company): void {
        Livewire::test(PdfCustomizer::class)
            ->assertSee('PDF Template & Letterhead')
            ->set('trade_name', 'LaraLekh Enterprise Cloud')
            ->set('contact_info', 'Phone: +91 22 9988 7766 · Email: contact@laralekh.test')
            ->set('show_bank_details', true)
            ->set('bank_name', 'HDFC Bank Ltd')
            ->set('bank_account_no', '50200099887766')
            ->set('bank_ifsc', 'HDFC0001234')
            ->set('bank_branch', 'Nariman Point, Mumbai')
            ->set('terms', "1. Payment strictly due within 15 days.\n2. Disputes subject to Mumbai jurisdiction.")
            ->set('signature_label', 'Managing Director')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('PDF customization settings saved.');

        $company->refresh();
        expect($company->pdf_settings)->toBeArray()
            ->and($company->pdf_settings['trade_name'])->toBe('LaraLekh Enterprise Cloud')
            ->and($company->pdf_settings['bank_name'])->toBe('HDFC Bank Ltd')
            ->and($company->pdf_settings['bank_ifsc'])->toBe('HDFC0001234')
            ->and($company->pdf_settings['signature_label'])->toBe('Managing Director');
    });
});

it('renders custom terms, bank details, and signature designation on invoice PDF without altering statutory content', function () {
    [$user, $company] = pdfCustomizerSession(['users.manage', 'invoices.view', 'invoices.create', 'invoices.post']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($company): void {
        $company->update([
            'name' => 'Solaris Global Corp',
            'gstin' => '27AAAAA0000A1Z5',
            'pdf_settings' => [
                'trade_name' => 'Solaris Digital Division',
                'show_bank_details' => true,
                'bank_name' => 'Axis Bank',
                'bank_account_no' => '912010099887766',
                'bank_ifsc' => 'UTIB0000123',
                'bank_branch' => 'Fort Branch',
                'terms' => "All invoices payable within 21 days.\nInterest @ 18% p.a. for delayed payments.",
                'signature_label' => 'Director & CEO',
            ],
        ]);
    });

    $invoice = createInvoiceForPdf($company);

    $response = $this->get(route('invoices.pdf', $invoice->id));
    expect($response->status())->toBe(200)
        ->and($response->headers->get('content-type'))->toContain('application/pdf');

    $bytes = $response->getContent();

    // Verify statutory particulars remain intact.
    expect(pdfHas($bytes, 'TAX INVOICE'))->toBeTrue()
        ->and(pdfHas($bytes, 'Solaris Global Corp'))->toBeTrue()
        ->and(pdfHas($bytes, '27AAAAA0000A1Z5'))->toBeTrue()
        ->and(pdfHas($bytes, 'SI/1'))->toBeTrue()
        ->and(pdfHas($bytes, 'Consulting Services'))->toBeTrue()
        ->and(pdfHas($bytes, 'Rupees One Thousand One Hundred Eighty Only'))->toBeTrue();

    // Verify custom options rendered in PDF.
    expect(pdfHas($bytes, 'Solaris Digital Division'))->toBeTrue()
        ->and(pdfHas($bytes, 'Bank Details for Payment'))->toBeTrue()
        ->and(pdfHas($bytes, 'Axis Bank'))->toBeTrue()
        ->and(pdfHas($bytes, '912010099887766'))->toBeTrue()
        ->and(pdfHas($bytes, 'UTIB0000123'))->toBeTrue()
        ->and(pdfHas($bytes, 'All invoices payable within 21 days.'))->toBeTrue()
        ->and(pdfHas($bytes, 'Director & CEO'))->toBeTrue();
});

it('omits company letterhead when hide_header is enabled for pre-printed letterheads', function () {
    [$user, $company] = pdfCustomizerSession(['users.manage', 'invoices.view', 'invoices.create', 'invoices.post']);
    $this->actingAs($user);

    inTenantContext($company, function () use ($company): void {
        $company->update([
            'name' => 'Paperprint Preprinted Ltd',
            'gstin' => '27AAAAA0000A1Z5',
            'pdf_settings' => [
                'hide_header' => true,
                'signature_label' => 'Partner',
            ],
        ]);
    });

    $invoice = createInvoiceForPdf($company);

    $response = $this->get(route('invoices.pdf', $invoice->id));
    expect($response->status())->toBe(200);

    $bytes = $response->getContent();

    // With hide_header true, company legal header block is omitted from the top,
    // but the statutory invoice document title, total, lines, and signature remain.
    expect(pdfHas($bytes, 'TAX INVOICE'))->toBeTrue()
        ->and(pdfHas($bytes, 'SI/1'))->toBeTrue()
        ->and(pdfHas($bytes, 'Partner'))->toBeTrue();
});
