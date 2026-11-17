<?php

use App\Domain\Accounting\Services\VoucherNumberAllocator;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Purchase\Actions\PostPayment;
use App\Domain\Purchase\Actions\PostPurchaseBill;
use App\Livewire\Masters\FinancialYearManager;
use Livewire\Livewire;

// Financial-year management screen tests (LL-P1-003). Company-scoped
// create/close behind years.manage; closing is permanent and posting into a
// closed year is rejected by the posting engine independently of this screen.

function fyManagerSession(array $permissions, string $email = 'fy@example.test'): array
{
    return webSession('fy-clerk', $permissions, $email);
}

it('requires years.manage to open the screen and guards every state change', function () {
    [$user, $company] = fyManagerSession(['years.manage']);
    $this->actingAs($user);
    $this->get(route('financial-years.index'))->assertOk();

    // Per-method guards do not rely on mount-time checks alone: mount as the
    // authorised user, then switch to a view-only user for the calls.
    $component = inTenantContext($company, fn () => Livewire::test(FinancialYearManager::class));

    $viewer = webUser('fy-viewer@example.test');
    addMembership($viewer, $company);
    $this->actingAs($viewer);
    $this->get(route('financial-years.index'))->assertForbidden();
    inTenantContext($company, function () use ($component): void {
        assert403(fn () => $component->instance()->create());
        assert403(fn () => $component->instance()->requestClose(1));
        assert403(fn () => $component->instance()->close(1));
    });
});

it('creates a financial year for the session company', function () {
    [$user, $company] = fyManagerSession(['years.manage']);
    $this->actingAs($user);
    inTenantContext($company, function () use ($company): void {
        Livewire::test(FinancialYearManager::class)
            ->set('name', '2027-28')->set('startsOn', '2027-04-01')->set('endsOn', '2028-03-31')
            ->call('create');

        $year = FinancialYear::where('name', '2027-28')->sole();
        expect($year->starts_on->toDateString())->toBe('2027-04-01')
            ->and($year->is_closed)->toBeFalse();
        // The new year belongs to the session company only (the session's own
        // 2026-27 year from webSession also exists).
        expect(FinancialYear::count())->toBe(2)->and($year->company_id)->toBe($company->id);
    });
});

it('rejects invalid labels, reversed dates and duplicate names without crashing', function () {
    [$user, $company] = fyManagerSession(['years.manage']);
    $this->actingAs($user);
    inTenantContext($company, function (): void {
        $component = Livewire::test(FinancialYearManager::class)
            ->set('name', 'not-a-year')->set('startsOn', '2027-04-01')->set('endsOn', '2028-03-31')
            ->call('create')->assertHasErrors(['name']);
        expect(FinancialYear::count())->toBe(1); // only the session's own year

        $component->set('name', '2027-28')->set('startsOn', '2028-03-31')->set('endsOn', '2027-04-01')
            ->call('create')->assertHasErrors(['dates']);
        expect(FinancialYear::count())->toBe(1);

        Livewire::test(FinancialYearManager::class)
            ->set('name', '2027-28')->set('startsOn', '2027-04-01')->set('endsOn', '2028-03-31')
            ->call('create');
        // The company+name unique index is surfaced as a field error.
        Livewire::test(FinancialYearManager::class)
            ->set('name', '2027-28')->set('startsOn', '2027-04-01')->set('endsOn', '2028-03-31')
            ->call('create')->assertHasErrors(['name']);
        expect(FinancialYear::count())->toBe(2); // baseline year + the one created
    });
});

it('closes a year permanently and the posting engine rejects it afterwards', function () {
    [$user, $company] = fyManagerSession(['years.manage', 'purchases.view', 'purchases.create', 'purchases.post']);
    $this->actingAs($user);
    $paymentId = inTenantContext($company, function (): int {
        $bill = supplierBillFixture();
        app(PostPurchaseBill::class)->handle($bill->id);

        return supplierPaymentDraft($bill->party, '1180.00')->id;
    });

    inTenantContext($company, function () use ($company, $paymentId): void {
        $component = Livewire::test(FinancialYearManager::class);
        $year = FinancialYear::sole();
        $component->call('requestClose', $year->id)->call('close', $year->id);

        expect($year->fresh()->is_closed)->toBeTrue();

        // Posting into the closed year now fails through the payment engine.
        expect(fn () => app(PostPayment::class)->post($paymentId, []))
            ->toThrow(DomainException::class, 'open financial year');
        // And number allocation fails independently.
        expect(fn () => app(VoucherNumberAllocator::class)->next($year->id, 'SP'))
            ->toThrow(DomainException::class, 'closed');
        // No reopen action exists in the component.
        expect(method_exists(FinancialYearManager::class, 'reopen'))->toBeFalse()
            ->and($company->id)->toBe($year->company_id);
    });
});

it('lists years with open and closed status for the session company only', function () {
    [$user, $company] = fyManagerSession(['years.manage']);
    $other = companyFixture('Other FY Co');
    addMembership($user, $other);
    $this->actingAs($user);

    $foreignYearId = inTenantContext($other, function (): int {
        return FinancialYear::create(['name' => '2030-31', 'starts_on' => '2030-04-01', 'ends_on' => '2031-03-31'])->id;
    });
    inTenantContext($company, function () use ($foreignYearId): void {
        FinancialYear::create(['name' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31']);
        Livewire::test(FinancialYearManager::class)
            ->assertSee('2027-28', false)
            ->assertDontSee('2030-31', false);
        expect(FinancialYear::whereKey($foreignYearId)->exists())->toBeFalse();
    });
});
