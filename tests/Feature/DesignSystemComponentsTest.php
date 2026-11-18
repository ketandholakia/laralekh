<?php

use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;

// Stage 0 shared design-system foundations: rendering, semantics and
// accessibility contracts for the components introduced or enhanced in
// MS-06 Stage 0. Tests assert markup, not pixels.

it('x-field associates the error message with the control and renders it with role=alert', function () {
    $html = Blade::render('<x-field label="Amount" name="amount" :error="$e"><x-input name="amount" aria-describedby="amount-error" aria-invalid="true"/></x-field>', ['e' => 'Enter a positive amount.']);

    expect($html)->toContain('id="amount-error"')
        ->and($html)->toContain('role="alert"')
        ->and($html)->toContain('aria-describedby="amount-error"')
        ->and($html)->toContain('aria-invalid="true"')
        ->and($html)->toContain('Enter a positive amount.');
});

it('x-field renders hint (without error) or the error, never both', function () {
    $withHint = Blade::render('<x-field label="Ref" name="ref" hint="As printed on the bill"><x-input name="ref"/></x-field>');
    expect($withHint)->toContain('As printed on the bill')->and($withHint)->not->toContain('role="alert"');

    $withError = Blade::render('<x-field label="Ref" name="ref" hint="As printed on the bill" :error="$e"><x-input name="ref"/></x-field>', ['e' => 'Required.']);
    expect($withError)->not->toContain('As printed on the bill')->and($withError)->toContain('Required.');
});

it('x-textarea renders an accessible label, hint and error wiring', function () {
    $hint = Blade::render('<x-textarea label="Narration" name="narration" hint="Keep it short">Blue ink only</x-textarea>');
    expect($hint)->toContain('for="narration"')
        ->and($hint)->toContain('id="narration"')
        ->and($hint)->toContain('aria-describedby="narration-hint"')
        ->and($hint)->toContain('aria-invalid="false"')
        ->and($hint)->toContain('Blue ink only');

    $error = Blade::render('<x-textarea label="Narration" name="narration" error="Too long.">draft</x-textarea>');
    expect($error)->toContain('aria-invalid="true"')
        ->and($error)->toContain('aria-describedby="narration-error"')
        ->and($error)->toContain('id="narration-error"')
        ->and($error)->toContain('role="alert"');
});

it('x-table renders a captioned semantic table with scopeable headers', function () {
    $html = Blade::render('<x-table caption="Invoices"><thead><tr><th scope="col">No</th></tr></thead><tbody><tr><td>1</td></tr></tbody></x-table>');

    expect($html)->toContain('<table')
        ->and($html)->toContain('<caption')
        ->and($html)->toContain('Invoices')
        ->and($html)->toContain('scope="col"')
        ->and($html)->toContain('overflow-x-auto');
});

it('x-table omits the caption element when none is passed', function () {
    $html = Blade::render('<x-table><tbody><tr><td>x</td></tr></tbody></x-table>');
    expect($html)->not->toContain('<caption');
});

it('x-detail-list renders a semantic dl/dt/dd grid with values and dash fallbacks', function () {
    $rows = [
        ['label' => 'Number', 'value' => 'SI/1'],
        ['label' => 'Total', 'value' => null, 'mono' => true],
    ];
    $html = Blade::render('<x-detail-list :rows="$rows" />', ['rows' => $rows]);

    expect($html)->toContain('<dl')
        ->and($html)->toContain('<dt')
        ->and($html)->toContain('<dd')
        ->and($html)->toContain('SI/1')
        ->and($html)->toContain('—')
        ->and($html)->toContain('tabular-nums');
});

it('x-dialog is a labelled role=dialog with title, description and a close control', function () {
    $html = Blade::render('<x-dialog id="confirm" title="Post invoice?" description="This cannot be undone." icon="alert">Body<x-slot:actions><button>OK</button></x-slot:actions></x-dialog>');

    expect($html)->toContain('id="confirm"')
        ->and($html)->toContain('role="dialog"')
        ->and($html)->toContain('aria-modal="true"')
        ->and($html)->toContain('aria-labelledby="confirm-title"')
        ->and($html)->toContain('id="confirm-title"')
        ->and($html)->toContain('Post invoice?')
        ->and($html)->toContain('This cannot be undone.')
        ->and($html)->toContain('aria-label="Close dialog"');
});

it('x-combobox exposes combobox/listbox semantics and keyboard wiring', function () {
    $html = Blade::render('<x-combobox name="party" placeholder="Search party"><button role="option" type="button">Acme Corp</button></x-combobox>');

    expect($html)->toContain('role="combobox"')
        ->and($html)->toContain('aria-expanded')
        ->and($html)->toContain('aria-controls="party-listbox"')
        ->and($html)->toContain('aria-autocomplete="list"')
        ->and($html)->toContain('role="listbox"')
        ->and($html)->toContain('role="option"')
        ->and($html)->toContain('@keydown.arrow-down.prevent')
        ->and($html)->toContain('@keydown.arrow-up.prevent')
        ->and($html)->toContain('@keydown.enter.prevent')
        ->and($html)->toContain('@keydown.escape.prevent');
});

it('x-input keeps the design-system focus contract and stable id wiring', function () {
    $html = Blade::render('<x-input name="amount"/>');
    expect($html)->toContain('id="amount"')->and($html)->toContain('name="amount"')->and($html)->toContain('focus:ring-brand-600/20');
});

it('pagination default view renders Previous/Next with accessible labels and page state', function () {
    expect(AbstractPaginator::$defaultView)->toBe('pagination.laralekh');

    // Render the registered view with a minimal length-aware paginator.
    $items = collect(range(1, 25))->forPage(2, 10)->values();
    $paginator = new Illuminate\Pagination\LengthAwarePaginator($items, 25, 10, 2, ['path' => '/test']);
    $html = Blade::render('{{ $paginator->links() }}', ['paginator' => $paginator]);

    expect($html)->toContain('x-pagination')
        ->and($html)->toContain('aria-current="page"')
        ->and($html)->toContain('rel="prev"')
        ->and($html)->toContain('rel="next"')
        ->and($html)->toContain('aria-label="Go to page 2"')
        ->and($html)->toContain('aria-current="page"')
        ->and($html)->toContain('Previous')
        ->and($html)->toContain('Next')
        ->and($html)->toContain('Showing')
        ->and($html)->toContain('11');
});
