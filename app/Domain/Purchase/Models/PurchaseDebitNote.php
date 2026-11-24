<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Models;

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseDebitNote extends TenantModel
{
    protected $casts = [
        'date' => 'immutable_date',
        'supplier_snapshot' => 'array',
        'taxable_amount' => MoneyCast::class,
        'cgst' => MoneyCast::class,
        'sgst' => MoneyCast::class,
        'utgst' => MoneyCast::class,
        'igst' => MoneyCast::class,
        'cess' => MoneyCast::class,
        'total' => MoneyCast::class,
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseDebitNoteLine::class)->orderBy('position');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
