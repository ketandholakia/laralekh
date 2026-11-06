<?php
declare(strict_types=1);
namespace App\Domain\Gst\ValueObjects;
use App\Domain\Shared\ValueObjects\Money;

final readonly class GstBreakdown
{
    public function __construct(
        public Money $taxableAmount,
        public Money $cgst,
        public Money $sgst,
        public Money $utgst,
        public Money $igst,
        public Money $cess,
        public Money $total,
        public Money $recipientTax,
        public Money $recipientCess,
        public string $category,
    ) {}

    public function amounts(): array
    {
        return ['taxable_amount' => $this->taxableAmount, 'cgst' => $this->cgst,
            'sgst' => $this->sgst, 'utgst' => $this->utgst, 'igst' => $this->igst,
            'cess' => $this->cess, 'total' => $this->total];
    }
}
