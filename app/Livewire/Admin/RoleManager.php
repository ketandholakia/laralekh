<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Domain\Shared\Models\Role;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Permission;

#[Layout('layouts.app')]
final class RoleManager extends Component
{
    public bool $showRoleModal = false;

    public ?int $editingRoleId = null;

    public string $roleName = '';

    /** @var list<string> */
    public array $selectedPermissions = [];

    public function mount(): void
    {
        $this->guardManage();
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
        $user = auth()->user();
        $companyId = $this->companyId();
        abort_unless($user && $user->isMemberOf($companyId), 403);
        abort_unless($this->inCompanyScope(fn () => $user->can('roles.manage')), 403);
    }

    public function getPermissionCatalogProperty(): array
    {
        return [
            'Sales' => [
                'invoices.view' => 'View invoices & registers',
                'invoices.create' => 'Create & edit invoice drafts',
                'invoices.post' => 'Post immutable invoices',
            ],
            'Money in' => [
                'receipts.view' => 'View receipts & customer balances',
                'receipts.create' => 'Create customer receipt drafts',
                'receipts.post' => 'Post immutable customer receipts',
                'receipts.allocate' => 'Allocate unapplied advances',
                'receipts.undo' => 'Undo allocations',
                'receipts.reverse' => 'Post receipt reversals',
            ],
            'Purchases' => [
                'purchases.view' => 'View purchase bills & payments',
                'purchases.create' => 'Create & edit purchase bills/payments',
                'purchases.post' => 'Post immutable bills & payments',
            ],
            'Corrections & Reversals' => [
                'corrections.view' => 'View credit/debit notes & reversals',
                'corrections.create' => 'Create correction note drafts',
                'corrections.post' => 'Post credit/debit notes',
                'reversals.post' => 'Post accounting reversals',
            ],
            'Compliance & Tax' => [
                'returns.view' => 'View GST return worksheets & tables',
                'returns.prepare' => 'Prepare & export GST CSV returns',
                'returns.file' => 'Record official GST return filing',
            ],
            'Accounting & Reports' => [
                'reports.view' => 'View Trial Balance, P&L, Balance Sheet',
            ],
            'Inventory & Items' => [
                'items.view' => 'View items and product catalog',
                'items.manage' => 'Create, edit, and delete items',
            ],
            'Administration & Setup' => [
                'years.manage' => 'Create & close financial years',
                'users.view' => 'View company users',
                'users.manage' => 'Manage users, assign roles, reset passwords',
                'roles.manage' => 'Manage company roles & permissions',
            ],
        ];
    }

    public function getRolesProperty(): array
    {
        return $this->inCompanyScope(function (int $companyId): array {
            return Role::with('permissions')->get()->map(function (Role $role) use ($companyId): array {
                $userCount = DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('company_id', $companyId)
                    ->count();

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'user_count' => $userCount,
                    'permissions' => $role->permissions->pluck('name')->all(),
                ];
            })->all();
        });
    }

    public function openCreateModal(): void
    {
        $this->guardManage();
        $this->reset(['roleName', 'selectedPermissions', 'editingRoleId']);
        $this->resetErrorBag();
        $this->showRoleModal = true;
    }

    public function openEditModal(int $roleId): void
    {
        $this->guardManage();
        $this->inCompanyScope(function () use ($roleId): void {
            $role = Role::with('permissions')->findOrFail($roleId);
            $this->editingRoleId = $role->id;
            $this->roleName = $role->name;
            $this->selectedPermissions = $role->permissions->pluck('name')->all();
            $this->resetErrorBag();
            $this->showRoleModal = true;
        });
    }

    public function closeRoleModal(): void
    {
        $this->showRoleModal = false;
        $this->reset(['roleName', 'selectedPermissions', 'editingRoleId']);
        $this->resetErrorBag();
    }

    public function applyTemplate(string $template): void
    {
        $allPermissions = [];
        foreach ($this->permissionCatalog as $group) {
            foreach (array_keys($group) as $perm) {
                $allPermissions[] = $perm;
            }
        }

        switch ($template) {
            case 'admin':
                $this->selectedPermissions = $allPermissions;
                break;
            case 'accountant':
                $this->selectedPermissions = array_values(array_diff($allPermissions, [
                    'years.manage', 'users.view', 'users.manage', 'roles.manage',
                ]));
                break;
            case 'sales_clerk':
                $this->selectedPermissions = [
                    'invoices.view', 'invoices.create',
                    'receipts.view', 'receipts.create',
                    'corrections.view', 'corrections.create',
                    'items.view',
                ];
                break;
            case 'purchase_clerk':
                $this->selectedPermissions = [
                    'purchases.view', 'purchases.create',
                    'items.view',
                ];
                break;
            case 'viewer':
                $this->selectedPermissions = [
                    'invoices.view', 'receipts.view', 'purchases.view',
                    'corrections.view', 'returns.view', 'reports.view',
                    'items.view',
                ];
                break;
        }
    }

    public function saveRole(): void
    {
        $this->guardManage();

        $this->validate([
            'roleName' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_\-\s]+$/'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['string'],
        ]);

        $cleanName = trim($this->roleName);

        $this->inCompanyScope(function (int $companyId) use ($cleanName): void {
            $exists = Role::where('name', $cleanName)
                ->when($this->editingRoleId, fn ($q) => $q->where('id', '!=', $this->editingRoleId))
                ->exists();

            if ($exists) {
                $this->addError('roleName', 'A role with this name already exists in this company.');

                return;
            }

            $permissions = array_map(
                fn (string $name) => Permission::findOrCreate($name, 'web'),
                $this->selectedPermissions,
            );

            if ($this->editingRoleId) {
                $role = Role::findOrFail($this->editingRoleId);
                $role->name = $cleanName;
                $role->save();
                $role->syncPermissions($permissions);
                session()->flash('status', 'Role "'.$cleanName.'" updated successfully.');
            } else {
                $role = Role::create(['name' => $cleanName]);
                $role->syncPermissions($permissions);
                session()->flash('status', 'Role "'.$cleanName.'" created successfully.');
            }

            $this->closeRoleModal();
        });
    }

    public function deleteRole(int $roleId): void
    {
        $this->guardManage();

        $this->inCompanyScope(function (int $companyId) use ($roleId): void {
            $role = Role::findOrFail($roleId);

            $userCount = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('company_id', $companyId)
                ->count();

            if ($userCount > 0) {
                $this->addError('role', 'Cannot delete role "'.$role->name.'" because '.$userCount.' user(s) are assigned to it.');

                return;
            }

            $role->delete();
            session()->flash('status', 'Role "'.$role->name.'" deleted successfully.');
        });
    }

    public function render(): View
    {
        return view('livewire.admin.role-manager', [
            'roles' => $this->roles,
            'catalog' => $this->permissionCatalog,
        ]);
    }
}
