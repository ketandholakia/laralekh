<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Domain\Sales\Actions\CorrectionAccess;
use App\Domain\Sales\Models\SalesCorrection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
final class CorrectionView extends Component
{
    #[Locked]
    public int $noteId;

    public function boot(): void
    {
        app(CorrectionAccess::class)->authorize();
    }

    public function mount(int|string $correction): void
    {
        $this->noteId = (int) $correction;
    }

    public function render()
    {
        return view('livewire.sales.correction-view', ['note' => SalesCorrection::with('lines', 'voucher')->findOrFail($this->noteId)]);
    }
}
