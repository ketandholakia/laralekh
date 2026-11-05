<?php
use App\Domain\Shared\Tenancy\{CompanySession, TenantContext};
use App\Models\User;

it('scopes permissions to the company that granted the role', function () {
    $user = webUser();
    $a = companyFixture('A');
    $b = companyFixture('B');
    addMembership($user, $a);
    addMembership($user, $b);
    assignCompanyRole($user, $a, 'accountant', ['invoices.view']);
    $this->actingAs($user);

    // Under company A the permission resolves; the same user under company B
    // has no role there, so the permission must not leak across the boundary.
    expect(app(TenantContext::class)->run($a->id, fn () => $user->can('invoices.view')))->toBeTrue();

    // Switching companies through the session clears the cached relations.
    app(CompanySession::class)->select($user, $b->id);
    expect(app(TenantContext::class)->run($b->id, fn () => $user->can('invoices.view')))->toBeFalse();
});

it('keeps the permission verdict after switching back to the granting company', function () {
    $user = webUser();
    $a = companyFixture('A');
    $b = companyFixture('B');
    addMembership($user, $a);
    addMembership($user, $b);
    assignCompanyRole($user, $a, 'accountant', ['invoices.view']);
    $this->actingAs($user);
    app(CompanySession::class)->select($user, $b->id);
    expect(app(TenantContext::class)->run($b->id, fn () => $user->can('invoices.view')))->toBeFalse();
    app(CompanySession::class)->select($user, $a->id);
    expect(app(TenantContext::class)->run($a->id, fn () => $user->can('invoices.view')))->toBeTrue();
});

it('does not grant a permission whose role exists only in another company', function () {
    $user = webUser();
    $a = companyFixture('A');
    $b = companyFixture('B');
    addMembership($user, $a);
    addMembership($user, $b);
    assignCompanyRole($user, $a, 'accountant', ['invoices.view']);
    assignCompanyRole(webUser('other@example.test'), $b, 'accountant', ['invoices.view']);
    $this->actingAs($user);
    // A role named the same way exists in B, but this user was never assigned it there.
    expect(app(TenantContext::class)->run($b->id, fn () => $user->can('invoices.view')))->toBeFalse();
    // Re-entering the granting company through the sanctioned switch path.
    app(CompanySession::class)->select($user, $a->id);
    expect(app(TenantContext::class)->run($a->id, fn () => $user->can('invoices.view')))->toBeTrue();
});
