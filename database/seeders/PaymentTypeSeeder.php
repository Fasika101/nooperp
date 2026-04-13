<?php

namespace Database\Seeders;

use App\Models\Branch;
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

        foreach (Branch::query()->where('is_active', true)->orderBy('name')->get() as $branch) {
            PaymentType::firstOrCreate(
                [
                    'name' => 'On Account',
                    'branch_id' => $branch->id,
                ],
                [
                    'is_active' => true,
                    'is_accounts_receivable' => true,
                    'bank_account_id' => null,
                ]
            );
        }
    }
}
