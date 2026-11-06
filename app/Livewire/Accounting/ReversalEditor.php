<?php
declare(strict_types=1);
namespace App\Livewire\Accounting;
use App\Domain\Accounting\Actions\ReverseVoucher;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Sales\Actions\CorrectionAccess;
use Livewire\Attributes\{Layout, Locked};
use Livewire\Component;
use DomainException;
use Illuminate\Validation\ValidationException;

#[Layout('layouts.app')]
final class ReversalEditor extends Component
{
    #[Locked] public int $voucherId;
    public string $date = '';
    public string $reason = '';
    public bool $confirmed = false;
    public function boot(): void { app(CorrectionAccess::class)->authorize('reversals.post'); }
    public function mount(int|string $voucher): void { $this->voucherId = (int) $voucher; $this->date = now()->toDateString(); }
    public function post(): void
    {
        abort_unless(auth()->user()?->can('reversals.post'), 403);
        $this->resetErrorBag();
        try {
            $reversal = app(ReverseVoucher::class)->handle($this->voucherId, $this->date, $this->reason, $this->confirmed);
            $this->redirectRoute('reversals.show', $reversal->id);
        } catch (DomainException $e) { $this->addError('reversal', $e->getMessage()); }
        catch (ValidationException $e) { $this->addError('reversal', $e->validator->errors()->first()); }
    }
    public function render()
    {
        return view('livewire.accounting.reversal-editor', ['original' => Voucher::with('lines.ledger')->findOrFail($this->voucherId)]);
    }
}

