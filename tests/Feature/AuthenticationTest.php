<?php
use App\Http\Middleware\EnsureCompanyContext;
use App\Domain\Accounting\Models\LedgerGroup;
use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Shared\Tenancy\{CompanySession, TenantContext};
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
    $this->post(route('company.store'), ['company_id' => $mine->id])->assertRedirect('/invoices');
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
