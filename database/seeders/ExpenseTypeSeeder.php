<?php

namespace Database\Seeders;

use App\Models\ExpenseType;
use Illuminate\Database\Seeder;

class ExpenseTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Rent', 'is_recurring' => true, 'frequency' => 'monthly', 'day_of_month' => 1],
            ['name' => 'Utilities', 'is_recurring' => true, 'frequency' => 'monthly', 'day_of_month' => 15],
            ['name' => 'Supplies', 'is_recurring' => false],
            ['name' => 'Salaries', 'is_recurring' => true, 'frequency' => 'monthly', 'day_of_month' => 25],
            ['name' => 'Marketing', 'is_recurring' => false],
            ['name' => 'Equipment', 'is_recurring' => false],
            ['name' => 'Transport', 'is_recurring' => false],
            ['name' => 'Inventory Purchase', 'is_recurring' => false],
            ['name' => 'Other', 'is_recurring' => false],
        ];

        foreach ($types as $attrs) {
            $name = $attrs['name'];
            unset($attrs['name']);
            ExpenseType::updateOrCreate(
                ['name' => $name],
                array_merge(['is_active' => true], $attrs)
            );
        }
    }
}
