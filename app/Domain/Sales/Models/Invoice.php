<?php
namespace App\Domain\Sales\Models;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Masters\Models\Party;
class Invoice extends TenantModel
{
    protected $casts = ['date' => 'immutable_date', 'due_date' => 'immutable_date', 'taxable_amount' => MoneyCast::class,
        'cgst' => MoneyCast::class, 'sgst' => MoneyCast::class, 'utgst' => MoneyCast::class, 'igst' => MoneyCast::class,
        'cess' => MoneyCast::class, 'recipient_cess' => MoneyCast::class, 'cancelled_at' => 'immutable_datetime',
        'total' => MoneyCast::class, 'recipient_tax' => MoneyCast::class, 'supplier_snapshot' => 'array', 'party_snapshot' => 'array',
        'reverse_charge' => 'boolean', 'sez_authorized' => 'boolean', 'zero_rated_eligible' => 'boolean'];
    public function items() { return $this->hasMany(InvoiceItem::class); }
    public function party() { return $this->belongsTo(Party::class); }
    public function voucher() { return $this->belongsTo(Voucher::class); }
    public function corrections() { return $this->hasMany(SalesCorrection::class); }
}
