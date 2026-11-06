<?php
declare(strict_types=1);
namespace App\Domain\Sales\Models;
use App\Domain\Shared\Models\TenantModel;
use App\Domain\Shared\Casts\MoneyCast;

class SalesCorrectionLine extends TenantModel
{
    protected $casts = [
        'quantity' => 'decimal:3', 'rate' => 'decimal:3', 'taxable_amount' => MoneyCast::class,
        'cgst' => MoneyCast::class, 'sgst' => MoneyCast::class, 'utgst' => MoneyCast::class, 'igst' => MoneyCast::class, 'cess' => MoneyCast::class,
        'recipient_tax' => MoneyCast::class, 'total' => MoneyCast::class,
        'basis_cgst' => MoneyCast::class, 'basis_sgst' => MoneyCast::class, 'basis_utgst' => MoneyCast::class,
        'basis_igst' => MoneyCast::class, 'basis_cess' => MoneyCast::class, 'basis_recipient_tax' => MoneyCast::class,
    ];
    public function correction() { return $this->belongsTo(SalesCorrection::class, 'sales_correction_id'); }
    public function originalItem() { return $this->belongsTo(InvoiceItem::class, 'invoice_item_id'); }
}
