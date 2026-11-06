<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Shared\Support\AmountInWords;
use App\Domain\Shared\ValueObjects\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as Document;

final class CorrectionDocuments
{
    public function note(SalesCorrection $note): Document
    {
        $note->load(['lines' => fn ($q) => $q->orderBy('position')]);

        return $this->render('pdf.correction', [
            'note' => $note, 'words' => AmountInWords::inr($note->total),
        ], $note->status === 'posted' ? $note->series.'/'.$note->number : 'DRAFT - NO NUMBER ALLOCATED');
    }

    public function reversal(VoucherReversal $reversal): Document
    {
        $reversal->load('voucher');
        $total = Money::of('0');
        foreach ($reversal->entries_snapshot as $entry) {
            $total = $total->add(Money::of($entry['debit']));
        }

        return $this->render('pdf.reversal', [
            'reversal' => $reversal, 'total' => $total, 'words' => AmountInWords::inr($total),
        ], 'INTERNAL REVERSAL '.$reversal->voucher->series.'/'.$reversal->voucher->number);
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
