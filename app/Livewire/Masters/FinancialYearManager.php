<?php

declare(strict_types=1);

namespace App\Livewire\Masters;

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Shared\Tenancy\CompanySession;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Company-scoped financial-year administration: create a year and close it.
// Closing is one-way by design (posted documents are immutable, so reopened
// years would undermine the audit trail); no reopen action exists. Posting
// into a closed year is rejected by the posting services and the number
// allocator independently of this screen.
#[Layout('layouts.app')]
final class FinancialYearManager extends Component
{
    public string $name = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public ?int $confirmCloseId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('years.manage'), 403);
    }

    private function guard(): void
    {
        abort_unless(auth()->user()?->can('years.manage'), 403);
    }

    public function getYearsProperty(): array
    {
        return FinancialYear::query()->orderByDesc('starts_on')
            ->get(['id', 'name', 'starts_on', 'ends_on', 'is_closed'])
            ->map(fn (FinancialYear $y) => [
                'id' => $y->id, 'name' => $y->name,
                'starts_on' => $y->starts_on->format('d-m-Y'),
                'ends_on' => $y->ends_on->format('d-m-Y'),
                'is_closed' => $y->is_closed,
            ])->all();
    }

    public function create(): void
    {
        $this->guard();
        $errors = [];
        if (! preg_match('/^\d{4}-\d{2}$/', $this->name)) {
            $errors['name'][] = 'Use a financial-year label such as 2027-28.';
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->startsOn) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->endsOn)) {
            $errors['dates'][] = 'Provide the start and end dates.';
        } elseif ($this->endsOn <= $this->startsOn) {
            $errors['dates'][] = 'The end date must be after the start date.';
        }
        foreach ($errors as $field => $messages) {
            $this->addError($field, $messages[0]);
        }
        if ($errors !== []) {
            return;
        }
        try {
            FinancialYear::create(['name' => $this->name, 'starts_on' => $this->startsOn, 'ends_on' => $this->endsOn]);
        } catch (QueryException $e) {
            // Company-scoped unique indexes on name and start date.
            $this->addError('name', str_contains((string) $e->getMessage(), 'financial_years_name_unique')
                || (int) ($e->errorInfo[1] ?? 0) === 1062 ? 'A financial year with this label or start date already exists.' : 'The financial year could not be created.');

            return;
        }
        $this->reset('name', 'startsOn', 'endsOn');
        session()->flash('status', 'Financial year created.');
    }

    public function requestClose(int $id): void
    {
        $this->guard();
        $this->confirmCloseId = $id;
    }

    public function close(int $id): void
    {
        $this->guard();
        $this->confirmCloseId = null;
        // Closing is permanent; the model has no reopen path by design.
        FinancialYear::whereKey($id)->where('is_closed', false)->firstOrFail()->update(['is_closed' => true]);
        session()->flash('status', 'Financial year closed. Posting into it is no longer possible.');
    }

    public function render(): View
    {
        app(CompanySession::class)->companyId();

        return view('livewire.masters.financial-year-manager');
    }
}
