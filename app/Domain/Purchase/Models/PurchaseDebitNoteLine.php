<?php

declare(strict_types=1);

namespace App\Domain\Purchase\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseDebitNoteLine extends TenantModel
{
    protected $casts = [
        'quantity' => 'decimal:3',
        'taxable_amount' => MoneyCast::class,
        'cgst' => MoneyCast::class,
        'sgst' => MoneyCast::class,
        'utgst' => MoneyCast::class,
        'igst' => MoneyCast::class,
        'cess' => MoneyCast::class,
        'total' => MoneyCast::class,
    ];

    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(PurchaseDebitNote::class, 'purchase_debit_note_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(PurchaseBill::class, 'purchase_bill_id');
    }

    public function billItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseBillItem::class, 'purchase_bill_item_id');
    }
}
