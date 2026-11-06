<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Domain\Masters\Models\Company;
use App\Domain\Masters\Models\State;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Shared\Support\AmountInWords;
use App\Domain\Shared\Support\UpiPaymentQr;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

// Renders the A4 invoice/bill-of-supply PDF. Posted documents print from the
// stored supplier/party snapshots; drafts render a clearly marked preview that
// never allocates an invoice number.
final class InvoicePdfController
{
    // The invoice is resolved inside the entered tenant scope; implicit route
    // binding would run before the middleware, outside any company context.
    public function __invoke(int $invoiceId): Response
    {
        $user = auth()->user();
        $companyId = app(CompanySession::class)->companyId();
        abort_if($companyId === null, 403);
        abort_unless($user->isMemberOf($companyId) && $user->can('invoices.view'), 403);

        $invoice = Invoice::findOrFail($invoiceId);
        $invoice->load(['items' => fn ($q) => $q->orderBy('position'), 'party']);
        $isPosted = $invoice->status === 'posted';
        // Draft models may not have persisted totals; normalise for words/QR.
        $zero = Money::of('0');
        $total = $invoice->total ?? $zero;
        $supplier = $isPosted ? ($invoice->supplier_snapshot ?? []) : app(TenantContext::class)
            ->run(app(CompanySession::class)->companyId(), fn (): array => $this->liveSupplier());
        $party = $isPosted ? ($invoice->party_snapshot ?? []) : [
            'name' => $invoice->party->name, 'gstin' => $invoice->party->gstin,
            'state_code' => $invoice->party->state_code, 'address' => $invoice->party->address,
            'registration_type' => $invoice->party->registration_type,
        ];

        $allNonTaxable = $invoice->items->count() > 0
            && $invoice->items->every(fn ($item) => $item->tax_category !== 'taxable');
        $billOfSupply = ($supplier['gst_regime'] ?? 'regular') === 'composition' || $allNonTaxable;

        $paymentQr = null;
        if ($isPosted) {
            $upiId = (string) app(TenantContext::class)->run(app(CompanySession::class)->companyId(),
                fn (): ?string => Company::whereKey($companyId)->value('upi_id'));
            if ($upiId !== '' && UpiPaymentQr::isValidVpa($upiId)) {
                try {
                    $paymentQr = UpiPaymentQr::html(UpiPaymentQr::uri(
                        $upiId, (string) ($supplier['name'] ?? config('app.name')), $invoice->total,
                        'Invoice '.Str::limit($invoice->series.'/'.$invoice->number, 40, ''),
                    ));
                } catch (\InvalidArgumentException) {
                    $paymentQr = null; // never block an invoice on a payment convenience QR
                }
            }
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'supplier' => $supplier,
            'party' => $party,
            'isPosted' => $isPosted,
            'billOfSupply' => $billOfSupply,
            'placeOfSupply' => $this->stateLabel($invoice->place_of_supply),
            'words' => AmountInWords::inr($invoice->total),
            'paymentQr' => $paymentQr,
            'interState' => ! $invoice->igst->isZero(),
        ])->setPaper('a4', 'portrait');

        $filename = ($isPosted
            ? 'invoice-'.$invoice->series.'-'.$invoice->number
            : 'draft-invoice-preview').'.pdf';
        $this->drawPageFooters($pdf, $isPosted);

        return $pdf->download($filename);
    }

    // This DomPDF release neither substitutes {PAGE_NUM}/{PAGE_COUNT} inline
    // nor applies pre-render canvas scripts. Render first, then stamp the
    // footer onto every completed page, and let download() serialize that state.
    private function drawPageFooters(\Barryvdh\DomPDF\PDF $pdf, bool $isPosted): void
    {
        $dompdf = $pdf->getDomPDF();
        $dompdf->render(); // single render pass; no serialization yet
        $font = $dompdf->getFontMetrics()->getFont('helvetica', 'normal');
        $label = $isPosted ? '' : 'DRAFT PREVIEW - no invoice number has been allocated  ·  ';
        $dompdf->getCanvas()->page_text(24, 20, $label.'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7.5, [0.42, 0.45, 0.50]);
    }

    private function stateLabel(string $code): string
    {
        if ($code === '99') {
            return '99 · Overseas (other territory)';
        }
        $name = State::where('code', $code)->value('name');

        return $name !== null ? $code.' · '.$name : $code;
    }

    /** @return array<string, mixed> */
    private function liveSupplier(): array
    {
        $company = Company::whereKey(app(TenantContext::class)->id())->firstOrFail();

        return $company->only(['name', 'gstin', 'state_code', 'address', 'gst_regime']);
    }
}
