<?php

namespace Database\Seeders;

use App\Domain\Masters\Models\State;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Jammu and Kashmir', 'Himachal Pradesh', 'Punjab', 'Chandigarh', 'Uttarakhand', 'Haryana', 'Delhi',
            'Rajasthan', 'Uttar Pradesh', 'Bihar', 'Sikkim', 'Arunachal Pradesh', 'Nagaland', 'Manipur',
            'Mizoram', 'Tripura', 'Meghalaya', 'Assam', 'West Bengal', 'Jharkhand', 'Odisha', 'Chhattisgarh',
            'Madhya Pradesh', 'Gujarat', 'Daman and Diu (legacy)', 'Dadra and Nagar Haveli and Daman and Diu',
            'Maharashtra', 'Andhra Pradesh (legacy)', 'Karnataka', 'Goa', 'Lakshadweep', 'Kerala',
            'Tamil Nadu', 'Puducherry', 'Andaman and Nicobar Islands', 'Telangana', 'Andhra Pradesh', 'Ladakh',
        ];
        foreach ($names as $index => $name) {
            $code = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            State::updateOrCreate(['code' => $code], [
                'name' => $name, 'is_active' => ! in_array($code, ['25', '28'], true),
                'uses_utgst' => in_array($code, ['04', '26', '31', '35', '38'], true),
            ]);
        }
    }
}
