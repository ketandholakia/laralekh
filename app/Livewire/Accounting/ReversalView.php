<?php

declare(strict_types=1);

namespace App\Livewire\Accounting;

use App\Domain\Accounting\Models\VoucherReversal;
use App\Domain\Sales\Actions\CorrectionAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
final class ReversalView extends Component
{
    #[Locked]
    public int $reversalId;

    public function boot(): void
    {
        app(CorrectionAccess::class)->authorize();
    }

    public function mount(int|string $reversal): void
    {
        $this->reversalId = (int) $reversal;
    }

    public function render()
    {
        return view('livewire.accounting.reversal-view', ['reversal' => VoucherReversal::with('voucher')->findOrFail($this->reversalId)]);
    }
}
