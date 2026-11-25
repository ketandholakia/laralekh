<?php

declare(strict_types=1);

namespace App\Livewire\Gst\Returns;

use App\Domain\Gst\Returns\FiledPeriodBackdatingMonitor;
use App\Domain\Gst\Returns\Gstr3bSummaryBuilder;
use App\Domain\Gst\Returns\GstReturnFilingService;
use App\Domain\Gst\Returns\GstReturnSnapshotService;
use App\Domain\Gst\Returns\Models\GstReturnFiling;
use App\Domain\Gst\Returns\Models\GstReturnSnapshot;
use App\Domain\Gst\Returns\Models\Gstr2bImport;
use App\Domain\Gst\Returns\ReturnDatasetBuilder;
use App\Domain\Gst\Returns\ReturnValidationReport;
use App\Domain\Gst\Returns\Services\Gstr2bImportService;
use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Tenancy\CompanySession;
use DomainException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('GST return period')]
final class Detail extends Component
{
    use WithFileUploads;

    public string $period;

    public string $type = 'gstr1';

    public string $arn = '';

    public string $filedOn = '';

    public string $message = '';

    public string $annualTurnoverRupees = '';

    /** @var mixed */
    public $gstr2bFile = null;

    public string $reconciliationFilter = 'all';

    public function mount(string $period): void
    {
        abort_unless(auth()->user()->can('returns.view'), 403);
        abort_unless((bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period), 404);
        $this->period = $period;
    }

    public function generate(string $type): void
    {
        abort_unless(auth()->user()->can('returns.finalize'), 403);
        abort_unless(in_array($type, ['gstr1', 'gstr3b'], true), 404);
        try {
            $data = $this->dataset();
            $report = app(ReturnValidationReport::class)->forDataset($data);
            $data['validation'] = $report;
            if ($type === 'gstr3b') {
                $data['gstr3b_summary'] = app(Gstr3bSummaryBuilder::class)->build($data);
            }
            app(GstReturnSnapshotService::class)->generate($this->company(), $this->period, $type, $data, $type === 'gstr1' ? 'csv-tool-3.2.4' : 'worksheet-1');
            $this->message = 'Draft snapshot generated.';
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();
        }
    }

    public function finalize(int $id): void
    {
        abort_unless(auth()->user()->can('returns.finalize'), 403);
        try {
            app(GstReturnSnapshotService::class)->finalize($this->ownedSnapshot($id)->id);
            $this->message = 'Snapshot finalized.';
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();
        }
    }

    public function markFiled(int $id): void
    {
        abort_unless(auth()->user()->can('returns.finalize'), 403);
        try {
            app(GstReturnFilingService::class)->markFiled($this->ownedSnapshot($id)->id, $this->arn, $this->filedOn);
            $this->message = 'Filing recorded.';
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();
        }
    }

    public function importGstr2b(): void
    {
        abort_unless(auth()->user()->can('returns.finalize'), 403);
        $this->validate([
            'gstr2bFile' => 'required|file|max:10240',
        ]);

        try {
            $content = $this->gstr2bFile->get();
            $filename = $this->gstr2bFile->getClientOriginalName();
            app(Gstr2bImportService::class)->import($this->company(), $this->period, $content, $filename, auth()->id());
            $this->message = 'GSTR-2B statement imported and reconciled successfully.';
            $this->gstr2bFile = null;
        } catch (\Throwable $e) {
            $this->message = 'Import failed: '.$e->getMessage();
        }
    }

    public function deleteGstr2b(): void
    {
        abort_unless(auth()->user()->can('returns.finalize'), 403);
        try {
            Gstr2bImport::query()->where('period', $this->period)->delete();
            $this->message = 'GSTR-2B import removed.';
        } catch (\Throwable $e) {
            $this->message = $e->getMessage();
        }
    }

    private function ownedSnapshot(int $id): GstReturnSnapshot
    {
        return GstReturnSnapshot::query()->whereKey($id)->where('period', $this->period)->firstOrFail();
    }

    private function company(): Company
    {
        return Company::query()->findOrFail(app(CompanySession::class)->companyId());
    }

    private function dataset(): array
    {
        if ($this->annualTurnoverRupees !== '' && ! ctype_digit($this->annualTurnoverRupees)) {
            throw new DomainException('Annual aggregate turnover must be entered as whole rupees.');
        }

        return app(ReturnDatasetBuilder::class)->build($this->company(), $this->period, $this->annualTurnoverRupees === '' ? null : (int) $this->annualTurnoverRupees);
    }

    public function render(): View
    {
        try {
            $dataset = $this->dataset();
            $report = app(ReturnValidationReport::class)->forDataset($dataset);
        } catch (\Throwable $e) {
            $dataset = [];
            $report = ['valid' => false, 'errors' => [['message' => $e->getMessage()]], 'warnings' => [], 'error_count' => 1, 'warning_count' => 0];
        }
        $summary = [];
        $summaryError = null;
        if ($dataset && $report['valid']) {
            try {
                $dataset['validation'] = $report;
                $summary = app(Gstr3bSummaryBuilder::class)->build($dataset);
            } catch (\Throwable $e) {
                $summaryError = $e->getMessage();
            }
        }

        return view('livewire.gst.returns.detail', [
            'dataset' => $dataset,
            'report' => $report,
            'summary' => $summary,
            'summaryError' => $summaryError,
            'snapshots' => GstReturnSnapshot::query()->where('period', $this->period)->orderByDesc('version')->get(),
            'filings' => GstReturnFiling::query()->where('period', $this->period)->get(),
            'backdating' => app(FiledPeriodBackdatingMonitor::class)->warnings($this->period),
            'gstr2bImport' => Gstr2bImport::query()->where('period', $this->period)->first(),
        ]);
    }
}
