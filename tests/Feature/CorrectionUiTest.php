<?php
use App\Domain\Sales\Models\SalesCorrection;
use App\Domain\Accounting\Models\{NumberSequence, VoucherReversal};
use App\Domain\Sales\Actions\SaveSalesCorrection;
use App\Domain\Sales\Services\CorrectionService;
use App\Livewire\Sales\{CorrectionEditor, CorrectionView};
use App\Livewire\Accounting\ReversalEditor;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

const CORRECTION_PERMISSIONS = ['invoices.view', 'corrections.view', 'corrections.create', 'corrections.post', 'reversals.post'];
beforeEach(fn () => $this->travelTo(\Carbon\Carbon::parse('2026-11-05 12:00:00')));

it('saves and posts a correction from the thin editor', function () {
    [$user, $company] = webSession(permissions: CORRECTION_PERMISSIONS); $this->actingAs($user);
    inTenantContext($company, function () {
        $source = correctionSource();
        $ui = Livewire::test(CorrectionEditor::class, ['invoice' => $source->id])
            ->set('form.reason', 'Agreed commercial concession')->set('lines.0.selected', true)->set('lines.0.amount', '100.00')
            ->call('save')->assertHasNoErrors();
        expect(SalesCorrection::sole()->number)->toBeNull();
        $ui->call('post')->assertHasNoErrors()->assertRedirect(route('corrections.show', SalesCorrection::sole()->id));
        expect(SalesCorrection::sole()->status)->toBe('posted');
        $ui->call('post')->assertHasErrors('correction');
        expect(SalesCorrection::count())->toBe(1)->and(NumberSequence::where('series', 'CCN')->sole()->last_number)->toBe(1);
    });
});

it('enforces posting permission independently of drafting permission', function () {
    [$user, $company] = webSession(permissions: ['corrections.view', 'corrections.create']); $this->actingAs($user);
    inTenantContext($company, function () {
        $source = correctionSource();
        Livewire::test(CorrectionEditor::class, ['invoice' => $source->id])->call('post')->assertForbidden();
    });
});

it('rechecks membership on a Livewire mutation', function () {
    [$user, $company] = webSession(permissions: CORRECTION_PERMISSIONS); $this->actingAs($user);
    inTenantContext($company, function () use ($user, $company) {
        $ui = Livewire::test(CorrectionEditor::class, ['invoice' => correctionSource()->id]);
        DB::table('company_user')->where('user_id', $user->id)->where('company_id', $company->id)->delete();
        $ui->call('save')->assertForbidden();
        expect(SalesCorrection::count())->toBe(0);
    });
});

it('requires separate reversal permission and explicit confirmation', function () {
    [$user, $company] = webSession(permissions: CORRECTION_PERMISSIONS); $this->actingAs($user);
    inTenantContext($company, function () {
        $source = correctionSource();
        $ui = Livewire::test(ReversalEditor::class, ['voucher' => $source->voucher_id])->set('reason', 'Invoice entered twice')
            ->call('post')->assertHasErrors('reversal');
        expect(VoucherReversal::count())->toBe(0);
        $ui->set('confirmed', true)->call('post')->assertHasNoErrors()->assertRedirect();
        expect(VoucherReversal::count())->toBe(1);
    });
});

it('renders authorized routes and related corrections on the original invoice', function () {
    [$user, $company] = webSession(permissions: CORRECTION_PERMISSIONS); $this->actingAs($user);
    $source = inTenantContext($company, fn () => correctionSource());
    $this->get(route('corrections.create', $source->id))->assertOk()->assertSee('New correction');
    $this->get(route('invoices.show', $source->id))->assertOk()->assertSee('Corrections and reversals');
    $this->get(route('reversals.create', $source->voucher_id))->assertOk()->assertSee('Review full accounting reversal');
});

it('does not expose another company correction through a URL', function () {
    [$user, $company] = webSession(permissions: CORRECTION_PERMISSIONS); $this->actingAs($user);
    $other = companyFixture('Other tenant');
    $note = inTenantContext($other, fn () => saveCorrection(correctionSource(), '100.00'));
    $this->get(route('corrections.show', $note->id))->assertNotFound();
});

it('keeps posted notes read-only in the editor', function () {
    [$user, $company] = webSession(permissions: CORRECTION_PERMISSIONS); $this->actingAs($user);
    inTenantContext($company, function () {
        $note = app(CorrectionService::class)->post(saveCorrection(correctionSource())->id);
        Livewire::test(CorrectionEditor::class, ['correction' => $note->id])->assertRedirect(route('corrections.show', $note->id));
        Livewire::test(CorrectionView::class, ['correction' => $note->id])->assertDontSee('Edit draft');
    });
});
