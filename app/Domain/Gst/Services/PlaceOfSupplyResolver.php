<?php
declare(strict_types=1);
namespace App\Domain\Gst\Services;
use App\Domain\Gst\ValueObjects\ResolvedSupply;
use InvalidArgumentException;

final class PlaceOfSupplyResolver
{
    public function resolve(
        string $supplierState,
        ?string $destinationState,
        string $supplyType = 'domestic',
        string $rule = 'goods_movement',
        ?string $billToState = null,
        ?string $explicitState = null,
        bool $recipientRegistered = true,
    ): ResolvedSupply {
        $this->validateState($supplierState);
        if (!in_array($supplyType, ['domestic', 'export', 'sez'], true)) {
            throw new InvalidArgumentException('Unsupported supply type.');
        }
        if (!in_array($rule, ['goods_movement', 'bill_to_ship_to', 'goods_no_movement', 'general_services', 'special'], true)) {
            throw new InvalidArgumentException('Unsupported place-of-supply rule.');
        }
        if ($supplyType === 'export') {
            if ($destinationState !== '99') {
                throw new InvalidArgumentException('Exports require an overseas destination and separately verified eligibility.');
            }
            return new ResolvedSupply($supplierState, '99', 'export', true, false);
        }
        $place = match ($rule) {
            'bill_to_ship_to' => $billToState,
            'special' => $explicitState,
            'general_services' => $destinationState ?? ($recipientRegistered ? null : $supplierState),
            default => $destinationState,
        };
        if ($place === null) {
            throw new InvalidArgumentException('Place of supply cannot be inferred; provide the required location.');
        }
        $this->validateState($place);
        $interState = $supplyType === 'sez' || $supplierState !== $place;
        return new ResolvedSupply($supplierState, $place, $supplyType, $interState,
            !$interState && in_array($place, ['04', '26', '31', '35', '38'], true));
    }

    private function validateState(string $state): void
    {
        if (!preg_match('/^(0[1-9]|[12][0-9]|3[0-8]|97)$/D', $state) || in_array($state, ['25', '28'], true)) {
            throw new InvalidArgumentException('Unsupported or retired state code.');
        }
    }
}
