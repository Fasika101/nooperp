<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Cash', 'Card', 'Mobile Money', 'Bank Transfer'];

        foreach ($types as $name) {
            PaymentType::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}
