<?php
use App\Domain\Gst\Services\PlaceOfSupplyResolver;

it('uses goods movement destination', function () {
    $r = (new PlaceOfSupplyResolver)->resolve('27', '24');
    expect($r->placeOfSupply)->toBe('24')->and($r->interState)->toBeTrue();
});
it('uses the directing party location for bill-to ship-to', function () {
    $r = (new PlaceOfSupplyResolver)->resolve('27', '24', rule: 'bill_to_ship_to', billToState: '27');
    expect($r->placeOfSupply)->toBe('27')->and($r->interState)->toBeFalse();
});
it('uses goods location when there is no movement', function () {
    expect((new PlaceOfSupplyResolver)->resolve('27', '24', rule: 'goods_no_movement')->placeOfSupply)->toBe('24');
});
it('uses recipient location for ordinary registered services', function () {
    expect((new PlaceOfSupplyResolver)->resolve('27', '24', rule: 'general_services')->placeOfSupply)->toBe('24');
});
it('falls back to supplier state for unregistered services without address', function () {
    expect((new PlaceOfSupplyResolver)->resolve('27', null, rule: 'general_services', recipientRegistered: false)->placeOfSupply)->toBe('27');
});
it('requires a location for registered services', fn () => (new PlaceOfSupplyResolver)->resolve('27', null, rule: 'general_services'))
    ->throws(InvalidArgumentException::class);
it('requires reviewed location for special services', fn () => (new PlaceOfSupplyResolver)->resolve('27', '24', rule: 'special'))
    ->throws(InvalidArgumentException::class);
it('accepts reviewed location for special services', function () {
    expect((new PlaceOfSupplyResolver)->resolve('27', '24', rule: 'special', explicitState: '29')->placeOfSupply)->toBe('29');
});
it('treats same-state SEZ as inter-state', fn () => expect((new PlaceOfSupplyResolver)->resolve('27', '27', 'sez')->interState)->toBeTrue());
it('requires overseas destination for exports', fn () => (new PlaceOfSupplyResolver)->resolve('27', '27', 'export'))
    ->throws(InvalidArgumentException::class);
it('rejects unsupported state codes', fn ($state) => (new PlaceOfSupplyResolver)->resolve('27', $state))
    ->with(['00', '25', '28', '39', '99', '7'])->throws(InvalidArgumentException::class);
it('does not treat Delhi or Puducherry as UTGST jurisdictions', fn ($state) => expect((new PlaceOfSupplyResolver)->resolve($state, $state)->usesUtgst)->toBeFalse())
    ->with(['07', '34']);

