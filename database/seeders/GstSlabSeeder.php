<?php

namespace Database\Seeders;

use App\Domain\Gst\Models\TaxRate;
use Illuminate\Database\Seeder;

class GstSlabSeeder extends Seeder
{
    public function run(): void
    {
        $legalReference = 'Template only; UNVERIFIED. Verify HSN/SAC applicability and effective dates against applicable CBIC rate and cess notifications before use; see docs/gst-rules.md.';
        foreach ([
            ['0', '2025-09-22', null], ['5', '2025-09-22', null], ['18', '2025-09-22', null], ['40', '2025-09-22', null],
            ['5', '2017-07-01', '2025-09-21'], ['12', '2017-07-01', '2025-09-21'],
            ['18', '2017-07-01', '2025-09-21'], ['28', '2017-07-01', '2025-09-21'],
        ] as [$rate, $from, $to]) {
            $query = TaxRate::where('code', 'GST_'.$rate)->whereDate('effective_from', $from);
            if (! $query->exists()) {
                $query->create(['code' => 'GST_'.$rate, 'effective_from' => $from,
                    'rate' => $rate, 'effective_to' => $to, 'is_verified' => false, 'legal_reference' => $legalReference]);
            }
        }
    }
}
