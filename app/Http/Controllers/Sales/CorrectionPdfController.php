<?php
declare(strict_types=1);
namespace App\Http\Controllers\Sales;
use App\Domain\Sales\Actions\CorrectionAccess;
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Sales\Services\CorrectionDocuments;
use Illuminate\Http\Response;
final class CorrectionPdfController
{
    public function __invoke(int $correction): Response
    {
        app(CorrectionAccess::class)->authorize();
        $note = SalesCorrection::findOrFail($correction);
        return app(CorrectionDocuments::class)->note($note)->download(
            $note->status === 'posted' ? 'note-'.$note->series.'-'.$note->number.'.pdf' : 'draft-note.pdf');
    }
}

