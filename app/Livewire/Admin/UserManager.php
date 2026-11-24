<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Models\Role;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
final class UserManager extends Component
{
    public string $search = '';

    public bool $showCreateModal = false;

    public bool $showEditRolesModal = false;

    public bool $showResetPasswordModal = false;

    // Create user form
    public string $name = '';

    public string $email = '';

    public string $password = '';

    /** @var list<string> */
    public array $selectedRoles = [];

    // Edit roles form
    public ?int $editingUserId = null;

    public string $editingUserName = '';

    /** @var list<string> */
    public array $editingRoles = [];

    // Reset password form
    public ?int $passwordResetUserId = null;

    public string $passwordResetUserName = '';

    public string $newPassword = '';

    public function mount(): void
    {
        $this->guardView();
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

    private function guardView(): void
    {
        $user = auth()->user();
        $companyId = $this->companyId();
        abort_unless($user && $user->isMemberOf($companyId), 403);
        abort_unless($this->inCompanyScope(fn () => $user->can('users.view') || $user->can('users.manage')), 403);
    }

    private function guardManage(): void
    {
        $user = auth()->user();
        $companyId = $this->companyId();
        abort_unless($user && $user->isMemberOf($companyId), 403);
        abort_unless($this->inCompanyScope(fn () => $user->can('users.manage')), 403);
    }

    public function getCompanyUsersProperty(): array
    {
        return $this->inCompanyScope(function (int $companyId): array {
            $query = Company::findOrFail($companyId)->users()->withPivot('is_active', 'created_at');

            if ($this->search !== '') {
                $term = '%'.trim($this->search).'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            }

            return $query->orderBy('name')->get()->map(function (User $u) use ($companyId): array {
                $roles = $u->roles()->pluck('name')->all();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'is_active' => (bool) $u->pivot->is_active,
                    'roles' => $roles,
                    'joined_at' => $u->pivot->created_at ? $u->pivot->created_at->format('d-m-Y') : '—',
                    'is_self' => $u->id === auth()->id(),
                ];
            })->all();
        });
    }

    public function getAvailableRolesProperty(): array
    {
        return $this->inCompanyScope(fn () => Role::query()->orderBy('name')->pluck('name')->all());
    }

    public function openCreateModal(): void
    {
        $this->guardManage();
        $this->reset(['name', 'email', 'password', 'selectedRoles']);
        $this->resetErrorBag();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['name', 'email', 'password', 'selectedRoles']);
        $this->resetErrorBag();
    }

    public function saveUser(): void
    {
        $this->guardManage();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'selectedRoles' => ['array'],
            'selectedRoles.*' => ['string'],
        ]);

        $cleanEmail = strtolower(trim($this->email));
        $cleanName = trim($this->name);

        $this->inCompanyScope(function (int $companyId) use ($cleanEmail, $cleanName): void {
            $existingUser = User::where('email', $cleanEmail)->first();

            if ($existingUser) {
                $alreadyLinked = DB::table('company_user')
                    ->where('company_id', $companyId)
                    ->where('user_id', $existingUser->id)
                    ->exists();

                if ($alreadyLinked) {
                    $this->addError('email', 'A user with this email is already a member of this company.');

                    return;
                }

                DB::table('company_user')->insert([
                    'company_id' => $companyId,
                    'user_id' => $existingUser->id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($this->selectedRoles !== []) {
                    $existingUser->assignRole($this->selectedRoles);
                }
                $existingUser->forgetCachedPermissionRelations();
            } else {
                $user = User::create([
                    'name' => $cleanName,
                    'email' => $cleanEmail,
                    'password' => Hash::make($this->password),
                ]);

                DB::table('company_user')->insert([
                    'company_id' => $companyId,
                    'user_id' => $user->id,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($this->selectedRoles !== []) {
                    $user->assignRole($this->selectedRoles);
                }
                $user->forgetCachedPermissionRelations();
            }

            $this->closeCreateModal();
            session()->flash('status', 'User added to company successfully.');
        });
    }

    public function openEditRolesModal(int $userId): void
    {
        $this->guardManage();
        $this->inCompanyScope(function (int $companyId) use ($userId): void {
            $user = Company::findOrFail($companyId)->users()->where('users.id', $userId)->firstOrFail();

            $this->editingUserId = $user->id;
            $this->editingUserName = $user->name;
            $this->editingRoles = $user->roles()->pluck('name')->all();
            $this->resetErrorBag();
            $this->showEditRolesModal = true;
        });
    }

    public function closeEditRolesModal(): void
    {
        $this->showEditRolesModal = false;
        $this->reset(['editingUserId', 'editingUserName', 'editingRoles']);
        $this->resetErrorBag();
    }

    public function saveUserRoles(): void
    {
        $this->guardManage();

        $this->validate([
            'editingRoles' => ['array'],
            'editingRoles.*' => ['string'],
        ]);

        $this->inCompanyScope(function (int $companyId): void {
            $user = Company::findOrFail($companyId)->users()->where('users.id', $this->editingUserId)->firstOrFail();
            $user->syncRoles($this->editingRoles);
            $user->forgetCachedPermissionRelations();

            $this->closeEditRolesModal();
            session()->flash('status', 'User roles updated successfully.');
        });
    }

    public function openResetPasswordModal(int $userId): void
    {
        $this->guardManage();
        $this->inCompanyScope(function (int $companyId) use ($userId): void {
            $user = Company::findOrFail($companyId)->users()->where('users.id', $userId)->firstOrFail();

            $this->passwordResetUserId = $user->id;
            $this->passwordResetUserName = $user->name;
            $this->newPassword = '';
            $this->resetErrorBag();
            $this->showResetPasswordModal = true;
        });
    }

    public function closeResetPasswordModal(): void
    {
        $this->showResetPasswordModal = false;
        $this->reset(['passwordResetUserId', 'passwordResetUserName', 'newPassword']);
        $this->resetErrorBag();
    }

    public function saveNewPassword(): void
    {
        $this->guardManage();

        $this->validate([
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        $this->inCompanyScope(function (int $companyId): void {
            $user = Company::findOrFail($companyId)->users()->where('users.id', $this->passwordResetUserId)->firstOrFail();
            $user->update(['password' => Hash::make($this->newPassword)]);

            $this->closeResetPasswordModal();
            session()->flash('status', 'Password reset successfully for '.$user->name.'.');
        });
    }

    public function toggleUserStatus(int $userId): void
    {
        $this->guardManage();

        if ($userId === auth()->id()) {
            $this->addError('status', 'You cannot deactivate your own account in the active company.');

            return;
        }

        $this->inCompanyScope(function (int $companyId) use ($userId): void {
            $pivot = DB::table('company_user')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->first();

            if (! $pivot) {
                return;
            }

            $newActive = ! (bool) $pivot->is_active;

            DB::table('company_user')
                ->where('company_id', $companyId)
                ->where('user_id', $userId)
                ->update([
                    'is_active' => $newActive,
                    'updated_at' => now(),
                ]);

            $user = User::find($userId);
            $user?->forgetCachedPermissionRelations();

            $message = $newActive
                ? 'User membership reactivated.'
                : 'User membership deactivated in this company.';
            session()->flash('status', $message);
        });
    }

    public function render(): View
    {
        return view('livewire.admin.user-manager', [
            'users' => $this->companyUsers,
            'availableRoles' => $this->availableRoles,
        ]);
    }
}
