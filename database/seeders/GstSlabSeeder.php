<?php
namespace Database\Seeders;
use App\Domain\Gst\Models\TaxRate;
use Illuminate\Database\Seeder;

class GstSlabSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['0', '5', '12', '18', '28'] as $rate) {
            if (TaxRate::where('code', 'GST_'.$rate)->whereDate('effective_from', '2017-07-01')->exists()) {
                continue;
            }
            TaxRate::create(['code' => 'GST_'.$rate, 'effective_from' => '2017-07-01',
                'rate' => $rate, 'is_verified' => false,
                'legal_reference' => 'Requested historical slab template. Verify HSN/SAC, effective dates and notifications before use; see docs/gst-rules.md.',
            ]);
        }
    }
}

