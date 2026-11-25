<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Masters\Models\FinancialYear;
use App\Domain\Masters\Models\Party;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceReceiptVoucher extends TenantModel
{
    protected $casts = [
        'date' => 'immutable_date',
        'is_rate_determinable' => 'boolean',
        'is_interstate_determinable' => 'boolean',
        'gross_amount' => MoneyCast::class,
        'taxable_amount' => MoneyCast::class,
        'cgst' => MoneyCast::class,
        'sgst' => MoneyCast::class,
        'utgst' => MoneyCast::class,
        'igst' => MoneyCast::class,
        'cess' => MoneyCast::class,
        'party_snapshot' => 'array',
        'supplier_snapshot' => 'array',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function label(): string
    {
        return $this->number !== null ? $this->series.'/'.$this->number : $this->series.'/draft';
    }
}
