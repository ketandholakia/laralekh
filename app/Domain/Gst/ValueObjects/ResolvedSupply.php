<?php

declare(strict_types=1);

namespace App\Domain\Gst\ValueObjects;

final readonly class ResolvedSupply
{
    public function __construct(
        public string $supplierState,
        public string $placeOfSupply,
        public string $type,
        public bool $interState,
        public bool $usesUtgst,
    ) {}
}
