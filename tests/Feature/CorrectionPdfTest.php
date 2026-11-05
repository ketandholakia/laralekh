<?php
use App\Domain\Sales\Services\{CorrectionService, CorrectionDocuments};
use App\Domain\Accounting\Services\ReversalService;
use App\Domain\Accounting\Models\Voucher;
use Carbon\CarbonImmutable;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-11-05'));
afterEach(fn () => CarbonImmutable::setTestNow());

function savePdfExample(string $name, string $bytes): void
{
    if (getenv('LARAVIX_PDF_SAMPLES') === '1') {
        $directory = base_path('output/pdf');
        if (!is_dir($directory)) { mkdir($directory, 0777, true); }
        file_put_contents($directory.'/'.$name.'.pdf', $bytes);
    }
}

it('renders correction snapshots with particulars words and numbering', function (string $name, string $kind, string $amount, int $count, bool $posted) {
    inSalesCompany(function ($company) use ($name, $kind, $amount, $count, $posted) {
        $invoice = correctionSource([], [], $count);
        $service = app(CorrectionService::class);
        $note = $service->saveDraft(null, $invoice->id, $invoice->financial_year_id,
            correctionInput(['kind' => $kind, 'reason_code' => $kind === 'credit' ? 'goods_return' : 'undercharge',
                'treatment' => $posted ? 'gst' : 'commercial']),
            $invoice->items->map(fn ($i) => ['invoice_item_id' => $i->id, 'quantity' => '0', 'amount' => $amount])->all());
        if ($posted) { $note = $service->post($note->id); }
        $company->update(['name' => 'CHANGED LIVE NAME']);
        $invoice->party->update(['name' => 'CHANGED LIVE PARTY']);
        $before = Voucher::count();
        $bytes = app(CorrectionDocuments::class)->note($note)->getDomPDF()->output();
        expect(pdfHas($bytes, 'Original invoice: '.$invoice->series.'/'.$invoice->number))->toBeTrue()
            ->and(pdfHas($bytes, 'Amount in words:'))->toBeTrue()
            ->and(pdfHas($bytes, 'CHANGED LIVE'))->toBeFalse()
            ->and(pdfHas($bytes, $posted ? $note->series.'/'.$note->number : 'DRAFT PREVIEW'))->toBeTrue()
            ->and(Voucher::count())->toBe($before);
        if (!$posted) { expect($note->fresh()->number)->toBeNull()->and(pdfHas($bytes, 'NO GST LIABILITY CHANGE'))->toBeTrue(); }
        if ($count > 1) {
            expect(pdfPageCount($bytes))->toBeGreaterThan(1)
                ->and(substr_count(str_replace(' ', '', pdfText($bytes)), 'Description/HSN'))->toBeGreaterThan(1);
        }
        savePdfExample($name, $bytes);
    });
})->with([
    ['credit-full', 'credit', '1000.00', 1, true],
    ['credit-partial', 'credit', '250.00', 1, true],
    ['debit-note', 'debit', '100.00', 1, true],
    ['commercial-draft', 'credit', '250.00', 1, false],
    ['credit-multipage', 'credit', '250.00', 48, true],
]);

it('renders an internal reversal with immutable ledger descriptions', function () {
    inSalesCompany(function () {
        $invoice = correctionSource();
        $reversal = app(ReversalService::class)->reverse($invoice->voucher_id, $invoice->financial_year_id, '2026-11-05', 'Duplicate accounting entry');
        $invoice->voucher->lines->first()->ledger->update(['name' => 'CHANGED LIVE LEDGER']);
        $bytes = app(CorrectionDocuments::class)->reversal($reversal)->getDomPDF()->output();
        expect(pdfHas($bytes, 'INTERNAL ACCOUNTING REVERSAL'))->toBeTrue()
            ->and(pdfHas($bytes, 'NOT A STATUTORY GST CREDIT NOTE'))->toBeTrue()
            ->and(pdfHas($bytes, 'CHANGED LIVE LEDGER'))->toBeFalse()
            ->and(pdfHas($bytes, '1180.00'))->toBeTrue();
        savePdfExample('internal-reversal', $bytes);
    });
});

it('protects PDF routes with company membership and correction permission', function () {
    [$user, $company] = webSession(permissions: ['invoices.view', 'corrections.view']);
    $this->actingAs($user);
    $note = inTenantContext($company, fn () => saveCorrection(correctionSource()));
    $this->get(route('corrections.pdf', $note->id))->assertOk()->assertHeader('content-type', 'application/pdf');
    $other = inSalesCompany(function ($company) {
        $company->update(['gstin' => '27BBBBB0000B1Z5']);
        return saveCorrection(correctionSource());
    });
    $this->get(route('corrections.pdf', $other->id))->assertNotFound();
    \Illuminate\Support\Facades\DB::table('company_user')->where('user_id', $user->id)->delete();
    $this->get(route('corrections.pdf', $note->id))->assertRedirect(route('company.select'));
});

