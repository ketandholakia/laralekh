<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Accounting\Models\Ledger;
use App\Domain\Accounting\Models\Voucher;
use App\Domain\Masters\Models\Party;
use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Models\TenantModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerRefund extends TenantModel
{
    protected $casts = [
        'date' => 'immutable_date',
        'amount' => MoneyCast::class,
        'party_snapshot' => 'array',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerRefundAllocation::class, 'customer_refund_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(CustomerRefundReversal::class, 'original_customer_refund_id');
    }

    public function isReversed(): bool
    {
        return $this->reversals()->exists();
    }

    public function label(): string
    {
        return $this->number !== null ? $this->series.'/'.$this->number : $this->series.'/draft';
    }
}
