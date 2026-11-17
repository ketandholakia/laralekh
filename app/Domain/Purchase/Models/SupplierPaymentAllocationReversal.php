<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Models;

use App\Domain\Shared\Models\TenantModel;

// Immutable marker row: an allocation reversed together with its payment.
// Created by PostPayment::reverse; never updated or deleted. Its existence
// excludes the allocation from derived bill outstanding.
class SupplierPaymentAllocationReversal extends TenantModel
{
}
