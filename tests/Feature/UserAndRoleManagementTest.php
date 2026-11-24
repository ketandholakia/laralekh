<?php

declare(strict_types=1);

use App\Domain\Masters\Models\Company;
use App\Domain\Shared\Models\Role;
use App\Livewire\Admin\RoleManager;
use App\Livewire\Admin\UserManager;
use App\Models\User;
use Database\Seeders\StateSeeder;
use Database\Seeders\UserPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    (new StateSeeder)->run();
    (new UserPermissionSeeder)->run();
});

function adminSession(string $email = 'admin@example.test', array $permissions = ['users.view', 'users.manage', 'roles.manage']): array
{
    $company = companyFixture('Acme Corp');
    $user = webUser($email);
    addMembership($user, $company);
    assignCompanyRole($user, $company, 'admin-role', $permissions);
    selectSessionCompany($user, $company, salesYear($company));

    return [$user, $company];
}

it('restricts user management to users with users.view permission', function (): void {
    [$user, $company] = adminSession();
    $unauth = webUser('viewer@example.test');
    addMembership($unauth, $company);
    assignCompanyRole($unauth, $company, 'viewer-role', ['invoices.view']);
    selectSessionCompany($unauth, $company, salesYear($company));

    $this->actingAs($unauth);
    $this->get('/admin/users')->assertForbidden();

    inTenantContext($company, function (): void {
        Livewire::test(UserManager::class)->assertForbidden();
    });

    $this->actingAs($user);
    $this->get('/admin/users')->assertOk()->assertSee('Users & Access');
});

it('aborts user management mutations for users.view-only users', function (): void {
    [$admin, $company] = adminSession();
    $viewOnly = webUser('viewonly@example.test');
    addMembership($viewOnly, $company);
    assignCompanyRole($viewOnly, $company, 'users-viewer', ['users.view']);
    selectSessionCompany($viewOnly, $company, salesYear($company));

    $this->actingAs($admin);
    $component = inTenantContext($company, fn () => Livewire::test(UserManager::class));

    $this->actingAs($viewOnly);
    inTenantContext($company, function () use ($component): void {
        assert403(fn () => $component->instance()->openCreateModal());
        assert403(fn () => $component->instance()->saveUser());
        assert403(fn () => $component->instance()->openEditRolesModal(1));
        assert403(fn () => $component->instance()->saveUserRoles());
        assert403(fn () => $component->instance()->openResetPasswordModal(1));
        assert403(fn () => $component->instance()->saveNewPassword());
        assert403(fn () => $component->instance()->toggleUserStatus(1));
    });
});

it('creates a new user and assigns company-scoped roles', function (): void {
    [$admin, $company] = adminSession();
    $this->actingAs($admin);

    inTenantContext($company, function () use ($company): void {
        Role::create(['name' => 'Accountant']);

        Livewire::test(UserManager::class)
            ->set('name', 'Priya Sharma')
            ->set('email', 'priya@example.test')
            ->set('password', 'secret1234')
            ->set('selectedRoles', ['Accountant'])
            ->call('saveUser')
            ->assertHasNoErrors();

        $newUser = User::where('email', 'priya@example.test')->firstOrFail();
        expect($newUser->name)->toBe('Priya Sharma')
            ->and(Hash::check('secret1234', $newUser->password))->toBeTrue()
            ->and($newUser->isMemberOf($company->id))->toBeTrue()
            ->and($newUser->hasRole('Accountant'))->toBeTrue();
    });
});

it('links an existing user from another company and assigns local roles', function (): void {
    [$admin, $companyA] = adminSession();
    $companyB = companyFixture('Beta Ltd');
    $existingUser = webUser('existing@example.test');
    addMembership($existingUser, $companyB);

    $this->actingAs($admin);

    inTenantContext($companyA, function () use ($existingUser, $companyA): void {
        Role::create(['name' => 'Sales Lead']);

        Livewire::test(UserManager::class)
            ->set('name', 'Existing Guy')
            ->set('email', 'existing@example.test')
            ->set('password', 'doesnotmatter123')
            ->set('selectedRoles', ['Sales Lead'])
            ->call('saveUser')
            ->assertHasNoErrors();

        expect($existingUser->isMemberOf($companyA->id))->toBeTrue()
            ->and($existingUser->hasRole('Sales Lead'))->toBeTrue();
    });
});

it('prevents adding a user who is already a member of the current company', function (): void {
    [$admin, $company] = adminSession();
    $this->actingAs($admin);

    inTenantContext($company, function () use ($admin): void {
        Livewire::test(UserManager::class)
            ->set('name', 'Duplicate Admin')
            ->set('email', $admin->email)
            ->set('password', 'password123')
            ->call('saveUser')
            ->assertHasErrors(['email']);
    });
});

it('updates assigned company roles for a user', function (): void {
    [$admin, $company] = adminSession();
    $worker = webUser('worker@example.test');
    addMembership($worker, $company);

    $this->actingAs($admin);

    inTenantContext($company, function () use ($worker): void {
        Role::create(['name' => 'Billing Clerk']);
        Role::create(['name' => 'Senior Auditor']);

        Livewire::test(UserManager::class)
            ->call('openEditRolesModal', $worker->id)
            ->set('editingRoles', ['Billing Clerk', 'Senior Auditor'])
            ->call('saveUserRoles')
            ->assertHasNoErrors();

        expect($worker->fresh()->hasRole('Billing Clerk'))->toBeTrue()
            ->and($worker->fresh()->hasRole('Senior Auditor'))->toBeTrue();
    });
});

it('resets a user password', function (): void {
    [$admin, $company] = adminSession();
    $worker = webUser('worker2@example.test');
    addMembership($worker, $company);

    $this->actingAs($admin);

    inTenantContext($company, function () use ($worker): void {
        Livewire::test(UserManager::class)
            ->call('openResetPasswordModal', $worker->id)
            ->set('newPassword', 'brandNewSecret99')
            ->call('saveNewPassword')
            ->assertHasNoErrors();

        expect(Hash::check('brandNewSecret99', $worker->fresh()->password))->toBeTrue();
    });
});

it('deactivates and reactivates a user membership', function (): void {
    [$admin, $company] = adminSession();
    $worker = webUser('worker3@example.test');
    addMembership($worker, $company);

    $this->actingAs($admin);

    inTenantContext($company, function () use ($worker, $company): void {
        expect($worker->isMemberOf($company->id))->toBeTrue();

        // Deactivate
        Livewire::test(UserManager::class)
            ->call('toggleUserStatus', $worker->id);

        expect($worker->fresh()->isMemberOf($company->id))->toBeFalse();

        // Reactivate
        Livewire::test(UserManager::class)
            ->call('toggleUserStatus', $worker->id);

        expect($worker->fresh()->isMemberOf($company->id))->toBeTrue();
    });
});

it('prevents an admin from deactivating their own account in the active company', function (): void {
    [$admin, $company] = adminSession();
    $this->actingAs($admin);

    inTenantContext($company, function () use ($admin, $company): void {
        Livewire::test(UserManager::class)
            ->call('toggleUserStatus', $admin->id)
            ->assertHasErrors(['status']);

        expect($admin->fresh()->isMemberOf($company->id))->toBeTrue();
    });
});

it('restricts role management to users with roles.manage permission', function (): void {
    [$admin, $company] = adminSession();
    $userOnly = webUser('useradmin@example.test');
    addMembership($userOnly, $company);
    assignCompanyRole($userOnly, $company, 'user-admin', ['users.view', 'users.manage']);
    selectSessionCompany($userOnly, $company, salesYear($company));

    $this->actingAs($userOnly);
    $this->get('/admin/roles')->assertForbidden();

    inTenantContext($company, function (): void {
        Livewire::test(RoleManager::class)->assertForbidden();
    });

    $this->actingAs($admin);
    $this->get('/admin/roles')->assertOk()->assertSee('Roles & Permissions');
});

it('creates and edits custom roles with permissions and templates', function (): void {
    [$admin, $company] = adminSession();
    $this->actingAs($admin);

    inTenantContext($company, function (): void {
        // Create with template
        $ui = Livewire::test(RoleManager::class)
            ->call('openCreateModal')
            ->set('roleName', 'Billing Operator')
            ->call('applyTemplate', 'sales_clerk')
            ->call('saveRole')
            ->assertHasNoErrors();

        $role = Role::where('name', 'Billing Operator')->firstOrFail();
        expect($role->hasPermissionTo('invoices.view'))->toBeTrue()
            ->and($role->hasPermissionTo('receipts.create'))->toBeTrue()
            ->and($role->hasPermissionTo('purchases.create'))->toBeFalse();

        // Edit role
        $ui->call('openEditModal', $role->id)
            ->set('roleName', 'Senior Billing Operator')
            ->set('selectedPermissions', ['invoices.view', 'invoices.create', 'purchases.view'])
            ->call('saveRole')
            ->assertHasNoErrors();

        $updatedRole = $role->fresh();
        expect($updatedRole->name)->toBe('Senior Billing Operator')
            ->and($updatedRole->hasPermissionTo('purchases.view'))->toBeTrue()
            ->and($updatedRole->hasPermissionTo('receipts.create'))->toBeFalse();
    });
});

it('prevents deleting roles that currently have assigned users', function (): void {
    [$admin, $company] = adminSession();
    $worker = webUser('clerk@example.test');
    addMembership($worker, $company);

    $this->actingAs($admin);

    inTenantContext($company, function () use ($worker): void {
        $role = Role::create(['name' => 'Assigned Role']);
        $worker->assignRole($role);

        Livewire::test(RoleManager::class)
            ->call('deleteRole', $role->id)
            ->assertHasErrors(['role']);

        expect(Role::where('name', 'Assigned Role')->exists())->toBeTrue();

        // Remove assignment and delete
        $worker->removeRole($role);
        Livewire::test(RoleManager::class)
            ->call('deleteRole', $role->id)
            ->assertHasNoErrors();

        expect(Role::where('name', 'Assigned Role')->exists())->toBeFalse();
    });
});

it('keeps roles strictly company-scoped', function (): void {
    [$adminA, $companyA] = adminSession('adminA@example.test');
    [$adminB, $companyB] = adminSession('adminB@example.test');

    $this->actingAs($adminA);
    inTenantContext($companyA, function (): void {
        Role::create(['name' => 'Company A Exclusive Role']);
    });

    $this->actingAs($adminB);
    inTenantContext($companyB, function (): void {
        expect(Role::where('name', 'Company A Exclusive Role')->exists())->toBeFalse();
        $roles = Livewire::test(RoleManager::class)->viewData('roles');
        $names = array_column($roles, 'name');
        expect($names)->not->toContain('Company A Exclusive Role');
    });
});
