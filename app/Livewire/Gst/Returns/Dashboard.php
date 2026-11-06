<?php
declare(strict_types=1);
namespace App\Livewire\Gst\Returns;

use App\Domain\Gst\Returns\Models\{GstReturnFiling,GstReturnSnapshot};
use App\Domain\Masters\Models\Company;
use App\Domain\Purchase\Models\PurchaseBill;
use App\Domain\Sales\Models\{Invoice,SalesCorrection};
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\{Layout,Title};
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('GST returns')]
final class Dashboard extends Component
{
    public function mount(): void { abort_unless(auth()->user()->can('returns.view'), 403); }

    public function render(): View
    {
        $companyId = app(CompanySession::class)->companyId();
        // Use portable date filtering; all queries inherit the active tenant scope.
        $periods = collect();
        foreach ([Invoice::class => 'date', PurchaseBill::class => 'date', SalesCorrection::class => 'reporting_date'] as $model => $column) {
            $model::query()->where('status','posted')->whereNotNull($column)->orderBy($column)->pluck($column)
                ->each(fn ($date) => $periods->push(($date instanceof \DateTimeInterface ? $date : \Carbon\CarbonImmutable::parse($date))->format('Y-m')));
        }
        SalesCorrection::query()->where('status','posted')->whereNull('reporting_date')->pluck('date')
            ->each(fn ($date) => $periods->push(($date instanceof \DateTimeInterface ? $date : \Carbon\CarbonImmutable::parse($date))->format('Y-m')));
        GstReturnSnapshot::query()->pluck('period')->each(fn(string $period)=>$periods->push($period));
        GstReturnFiling::query()->pluck('period')->each(fn(string $period)=>$periods->push($period));
        $rows = $periods->unique()->sortDesc()->map(function (string $period) {
            $snapshots = GstReturnSnapshot::query()->where('period',$period)->get()->groupBy('type');
            $filings = GstReturnFiling::query()->where('period',$period)->get()->keyBy('type');
            return ['period'=>$period,'gstr1'=>$snapshots->get('gstr1',collect())->sortByDesc('version')->first(),'gstr3b'=>$snapshots->get('gstr3b',collect())->sortByDesc('version')->first(),'filings'=>$filings];
        });
        return view('livewire.gst.returns.dashboard',['periods'=>$rows,'company'=>Company::query()->findOrFail($companyId)]);
    }
}
