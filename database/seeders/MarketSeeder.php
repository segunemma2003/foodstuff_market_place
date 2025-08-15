<?php

namespace Database\Seeders;

use App\Models\Market;
use Illuminate\Database\Seeder;

class MarketSeeder extends Seeder
{
    public function run(): void
    {
        $markets = [
            [
                'name' => 'Central Market Lagos',
                'description' => 'The largest food market in Lagos',
                'address' => '123 Market Street, Lagos Island, Lagos',
                'latitude' => 6.5244,
                'longitude' => 3.3792,
                'phone' => '+2348012345678',
                'email' => 'central@foodstuff.store',
                'is_active' => true,
            ],
            [
                'name' => 'Victoria Island Market',
                'description' => 'Premium food market in Victoria Island',
                'address' => '456 Victoria Street, Victoria Island, Lagos',
                'latitude' => 6.4281,
                'longitude' => 3.4219,
                'phone' => '+2348023456789',
                'email' => 'victoria@foodstuff.store',
                'is_active' => true,
            ],
            [
                'name' => 'Ikeja Market',
                'description' => 'Popular market in Ikeja area',
                'address' => '789 Ikeja Road, Ikeja, Lagos',
                'latitude' => 6.6018,
                'longitude' => 3.3515,
                'phone' => '+2348034567890',
                'email' => 'ikeja@foodstuff.store',
                'is_active' => true,
            ],
        ];

        foreach ($markets as $market) {
            Market::create($market);
        }
    }
}
