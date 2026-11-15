<?php

use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Shared\Tenancy\CompanySession;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Http\Middleware\EnsureCompanyContext;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function storeSessionCompany(User $user, int $companyId): void
{
    app(CompanySession::class)->select($user, $companyId);
}

function contextRequest(User $user): Request
{
    $request = Request::create('/', 'GET');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $user);

    return $request;
}

it('rejects invalid credentials without logging in', function () {
    webUser();
    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => 'worker@example.test', 'password' => 'wrong-password',
    ]);
    $response->assertRedirect(route('login'))->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs in with valid credentials and requires company selection', function () {
    webUser();
    $response = $this->post(route('login.store'), [
        'email' => 'worker@example.test', 'password' => 'password123',
    ]);
    $response->assertRedirect(route('company.select'));
    $this->assertAuthenticated();
});

it('rate limits repeated failed logins', function () {
    webUser();
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), ['email' => 'worker@example.test', 'password' => 'nope']);
    }
    // The correct password is still rejected while the attempt limiter is hot.
    $this->post(route('login.store'), ['email' => 'worker@example.test', 'password' => 'password123'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('redirects guests away from the company selection screen', function () {
    $this->get(route('company.select'))->assertRedirect(route('login'));
});

it('logs out and forgets the selected company', function () {
    $user = webUser();
    $company = companyFixture();
    addMembership($user, $company);
    $this->actingAs($user);
    storeSessionCompany($user, $company->id);
    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
    expect(app(CompanySession::class)->companyId())->toBeNull();
});

it('accepts company selection only for members', function () {
    $user = webUser();
    $mine = companyFixture('Mine');
    $other = companyFixture('Other');
    addMembership($user, $mine);
    $this->actingAs($user);
    // A crafted request naming a company the user does not belong to is rejected.
    $this->post(route('company.store'), ['company_id' => $other->id])
        ->assertSessionHasErrors('company_id');
    expect(app(CompanySession::class)->companyId())->toBeNull();
    $this->post(route('company.store'), ['company_id' => $mine->id])->assertRedirect('/');
    expect(app(CompanySession::class)->companyId())->toBe($mine->id);
});

it('rejects a financial year that belongs to another company', function () {
    $user = webUser();
    $mine = companyFixture('Mine');
    $other = companyFixture('Other');
    addMembership($user, $mine);
    $foreignYear = app(TenantContext::class)->run($other->id, fn () => FinancialYear::create([
        'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31',
    ]));
    $this->actingAs($user);
    $this->post(route('company.store'), ['company_id' => $mine->id, 'financial_year_id' => $foreignYear->id])
        ->assertSessionHasErrors('financial_year_id');
    expect(app(CompanySession::class)->companyId())->toBeNull();
});

it('requires company selection before entering the tenant scope', function () {
    $user = webUser();
    $this->actingAs($user);
    $response = app(EnsureCompanyContext::class)->handle(contextRequest($user), fn () => response('inside'));
    expect($response->getTargetUrl())->toBe(route('company.select'));
});

it('forgets the selection when membership is revoked', function () {
    $user = webUser();
    $company = companyFixture();
    addMembership($user, $company);
    $this->actingAs($user);
    storeSessionCompany($user, $company->id);
    DB::table('company_user')->where('user_id', $user->id)->delete();
    $response = app(EnsureCompanyContext::class)->handle(contextRequest($user), fn () => response('inside'));
    expect($response->getTargetUrl())->toBe(route('company.select'))
        ->and(app(CompanySession::class)->companyId())->toBeNull();
});

it('enters the tenant scope for members', function () {
    $user = webUser();
    $company = companyFixture();
    addMembership($user, $company);
    $this->actingAs($user);
    storeSessionCompany($user, $company->id);
    $seen = null;
    app(EnsureCompanyContext::class)->handle(contextRequest($user), function () use (&$seen): void {
        $seen = LedgerGroup::create(['code' => 'A', 'name' => 'Assets', 'nature' => 'asset']);
    });
    expect($seen)->not->toBeNull()->and($seen->company_id)->toBe($company->id);
});

it('fails closed when the session year belongs to another company', function () {
    $user = webUser();
    $company = companyFixture();
    $foreign = companyFixture('Foreign');
    addMembership($user, $company);
    $this->actingAs($user);
    $foreignYear = app(TenantContext::class)->run($foreign->id, fn () => FinancialYear::create([
        'name' => '2026-27', 'starts_on' => '2026-04-01', 'ends_on' => '2027-03-31',
    ]));
    // Stale session state pointing at the other company's year.
    session(['company_id' => $company->id, 'financial_year_id' => $foreignYear->id]);
    expect(fn () => app(EnsureCompanyContext::class)->handle(contextRequest($user), fn () => response('inside')))
        ->toThrow(ModelNotFoundException::class);
});

// Regression: the layout serves both Livewire full-page components ($slot) and
// plain @extends views; rendering the guest login page used to fail with an
// undefined-variable 500 and was never covered because tests only POSTed.
it('renders the guest login page with LaraLekh branding', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('<title>LaraLekh</title>', false);
    // The inline brand mark carries an accessible LaraLekh label.
    $response->assertSee('aria-label="LaraLekh', false);
    $response->assertSee('name="email"', false);
    $response->assertSee('name="password"', false);
    $response->assertSee('Log in', false);
});

// Regression: before a company is selected no tenant context exists, so the
// company-scoped Role model cannot be queried; the layout must render the
// navigation without triggering the permission-backed GST returns link.
it('renders company selection before a company is selected without tenant-scoped navigation', function () {
    $user = webUser('selector@example.test');
    $company = companyFixture('Selector Co');
    addMembership($user, $company);

    $response = $this->actingAs($user)->get(route('company.select'));

    $response->assertOk();
    $response->assertSee('<title>LaraLekh</title>', false);
    $response->assertSee('name="company_id"', false);
    $response->assertSee('Selector Co', false);
    $response->assertDontSee('GST returns', false);
});

// Regression: once a company is selected, navigation pages inside the tenant
// scope must show the GST returns link for authorized users, while the
// selection screen itself stays tenant-safe (no context is established there).
it('shows the GST returns navigation after company selection for authorized users', function () {
    [$user, $company] = webSession('returns-clerk', ['invoices.view', 'returns.view']);
    $this->actingAs($user);

    $this->get(route('company.select'))
        ->assertOk()
        ->assertDontSee('GST returns', false);

    $this->get(route('invoices.index'))
        ->assertOk()
        ->assertSee('GST returns', false);
});

// The link is permission-gated: the tenant-safe rendering must not widen what
// an unauthorized user sees on pages inside the tenant scope.
it('hides the GST returns navigation for users without the returns.view permission', function () {
    [$user, $company] = webSession();
    $this->actingAs($user);

    $this->get(route('invoices.index'))
        ->assertOk()
        ->assertDontSee('GST returns', false);
});
