<?php
declare(strict_types=1);
namespace App\Domain\Shared\Models;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

final class TenantBuilder extends Builder
{
    public function update(array $values)
    {
        if (array_key_exists('company_id', $values)) {
            throw new LogicException('Company ownership is immutable, including bulk updates.');
        }
        return parent::update($values);
    }
}
