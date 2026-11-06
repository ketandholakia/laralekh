<?php

declare(strict_types=1);

namespace App\Domain\Shared\Casts;

use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::of($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return (string) ($value instanceof Money ? $value : Money::of($value));
    }
}
