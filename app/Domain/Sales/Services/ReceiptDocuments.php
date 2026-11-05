<?php
declare(strict_types=1);
namespace App\Domain\Sales\Services;

use App\Domain\Masters\Models\Party;
use App\Domain\Sales\Models\Receipt;
use App\Domain\Shared\Support\AmountInWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Document;

// Receipt and customer statement documents. A receipt is a commercial payment
// acknowledgement, NOT a statutory GST receipt voucher; unapplied amounts are
// advances whose GST treatment is outside this MVP and stated on the document.
final class ReceiptDocuments
{
    public function receipt(Receipt $receipt): Document
    {
        $receipt->load('ledger');
        $posted = $receipt->status === 'posted';
        // Only the allocations made when the receipt was posted appear on the
        // original document; later allocation history lives in the register.
        $original = collect($receipt->allocations_snapshot ?? [])->map(fn ($row) => (object) $row);
        $partyName = $receipt->party_snapshot['name'] ?? ($receipt->party?->name ?? '—');
        $partyAddress = $receipt->party_snapshot['address'] ?? ($receipt->party?->address ?? '');
        return $this->render('pdf.receipt', [
            'receipt' => $receipt, 'original' => $original, 'posted' => $posted,
            'partyName' => $partyName, 'partyAddress' => $partyAddress,
            'words' => AmountInWords::inr($receipt->amount),
        ], $posted ? 'RECEIPT '.$receipt->series.'/'.$receipt->number : 'DRAFT - NO NUMBER ALLOCATED');
    }

    public function statement(array $statement): Document
    {
        return $this->render('pdf.statement', [
            'statement' => $statement, 'party' => $statement['party'],
        ], 'CUSTOMER STATEMENT AS OF '.$statement['as_of']);
    }

    private function render(string $view, array $data, string $label): Document
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'portrait');
        $dom = $pdf->getDomPDF();
        $dom->render();
        $font = $dom->getFontMetrics()->getFont('helvetica', 'normal');
        $dom->getCanvas()->page_text(34, 20, $label.'  |  Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8);
        return $pdf;
    }
}
