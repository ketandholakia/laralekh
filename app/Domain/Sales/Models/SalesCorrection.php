<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Accounting\Models\Voucher;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;

class SalesCorrection extends TenantModel
{
    protected $casts = [
        'date' => 'immutable_date', 'reporting_date' => 'immutable_date', 'annual_return_filed_on' => 'immutable_date',
        'eligibility_confirmed' => 'boolean', 'supplier_snapshot' => 'array', 'party_snapshot' => 'array', 'original_snapshot' => 'array',
        'taxable_amount' => MoneyCast::class, 'cgst' => MoneyCast::class, 'sgst' => MoneyCast::class,
        'utgst' => MoneyCast::class, 'igst' => MoneyCast::class, 'cess' => MoneyCast::class, 'recipient_tax' => MoneyCast::class, 'total' => MoneyCast::class,
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lines()
    {
        return $this->hasMany(SalesCorrectionLine::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function label(): string
    {
        return ($this->treatment === 'gst' ? 'GST ' : 'Commercial ').($this->kind === 'credit' ? 'credit note' : 'debit note');
    }
}
