<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class PdfCustomizer extends Component
{
    public bool $hide_header = false;

    public string $trade_name = '';

    public string $contact_info = '';

    public string $logo_data = '';

    public bool $show_bank_details = false;

    public string $bank_name = '';

    public string $bank_account_no = '';

    public string $bank_ifsc = '';

    public string $bank_branch = '';

    public string $terms = '';

    public string $signature_label = 'Authorised signatory';

    public function mount(): void
    {
        $this->guardManage();
        $this->loadSettings();
    }

    private function companyId(): int
    {
        return app(TenantContext::class)->hasContext()
            ? app(TenantContext::class)->id()
            : (app(CompanySession::class)->companyId() ?? abort(403, 'A company context is required.'));
    }

    private function inCompanyScope(Closure $callback): mixed
    {
        $companyId = $this->companyId();
        if (app(TenantContext::class)->hasContext() && app(TenantContext::class)->id() === $companyId) {
            return $callback($companyId);
        }

        return app(TenantContext::class)->run($companyId, fn () => $callback($companyId));
    }

    private function guardManage(): void
    {
        $user = auth()->user() ?? abort(403);
        abort_unless($this->inCompanyScope(fn () => $user->can('users.manage') || $user->hasRole('admin')), 403);
    }

    public function loadSettings(): void
    {
        $this->inCompanyScope(function (int $companyId): void {
            $company = Company::findOrFail($companyId);
            $settings = $company->pdf_settings ?? [];

            $this->hide_header = (bool) ($settings['hide_header'] ?? false);
            $this->trade_name = (string) ($settings['trade_name'] ?? '');
            $this->contact_info = (string) ($settings['contact_info'] ?? '');
            $this->logo_data = (string) ($settings['logo_data'] ?? '');
            $this->show_bank_details = (bool) ($settings['show_bank_details'] ?? false);
            $this->bank_name = (string) ($settings['bank_name'] ?? '');
            $this->bank_account_no = (string) ($settings['bank_account_no'] ?? '');
            $this->bank_ifsc = (string) ($settings['bank_ifsc'] ?? '');
            $this->bank_branch = (string) ($settings['bank_branch'] ?? '');
            $this->terms = (string) ($settings['terms'] ?? '');
            $this->signature_label = (string) ($settings['signature_label'] ?? 'Authorised signatory');
        });
    }

    public function save(): void
    {
        $this->guardManage();

        $this->validate([
            'trade_name' => ['nullable', 'string', 'max:100'],
            'contact_info' => ['nullable', 'string', 'max:255'],
            'logo_data' => ['nullable', 'string', 'max:500000'],
            'bank_name' => ['nullable', 'string', 'max:80'],
            'bank_account_no' => ['nullable', 'string', 'max:35'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'bank_branch' => ['nullable', 'string', 'max:80'],
            'terms' => ['nullable', 'string', 'max:1000'],
            'signature_label' => ['nullable', 'string', 'max:60'],
        ]);

        $this->inCompanyScope(function (int $companyId): void {
            $company = Company::findOrFail($companyId);

            $settings = [
                'hide_header' => $this->hide_header,
                'trade_name' => trim($this->trade_name) !== '' ? trim($this->trade_name) : null,
                'contact_info' => trim($this->contact_info) !== '' ? trim($this->contact_info) : null,
                'logo_data' => trim($this->logo_data) !== '' ? trim($this->logo_data) : null,
                'show_bank_details' => $this->show_bank_details,
                'bank_name' => trim($this->bank_name) !== '' ? trim($this->bank_name) : null,
                'bank_account_no' => trim($this->bank_account_no) !== '' ? trim($this->bank_account_no) : null,
                'bank_ifsc' => trim($this->bank_ifsc) !== '' ? strtoupper(trim($this->bank_ifsc)) : null,
                'bank_branch' => trim($this->bank_branch) !== '' ? trim($this->bank_branch) : null,
                'terms' => trim($this->terms) !== '' ? trim($this->terms) : null,
                'signature_label' => trim($this->signature_label) !== '' ? trim($this->signature_label) : 'Authorised signatory',
            ];

            $company->update(['pdf_settings' => $settings]);
        });

        session()->flash('status', 'PDF customization settings saved.');
    }

    public function removeLogo(): void
    {
        $this->logo_data = '';
    }

    public function render(): View
    {
        return view('livewire.admin.pdf-customizer', [
            'company' => $this->inCompanyScope(fn (int $id) => Company::findOrFail($id)),
        ]);
    }
}
