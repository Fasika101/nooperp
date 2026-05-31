<?php

namespace Tests\Unit;

use App\Models\ExpenseType;
use App\Services\ExpenseExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseExporterFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtered_expense_type_ids_reads_multiple_values(): void
    {
        $rent = ExpenseType::query()->create(['name' => 'Rent', 'is_active' => true]);
        $utilities = ExpenseType::query()->create(['name' => 'Utilities', 'is_active' => true]);

        $ids = ExpenseExporter::filteredExpenseTypeIds([
            'expense_type_id' => ['values' => [$rent->id, $utilities->id]],
        ]);

        $this->assertSame([(int) $rent->id, (int) $utilities->id], $ids);
    }

    public function test_filtered_expense_type_ids_supports_single_value_for_backwards_compatibility(): void
    {
        $salaries = ExpenseType::query()->create(['name' => 'Salaries', 'is_active' => true]);

        $ids = ExpenseExporter::filteredExpenseTypeIds([
            'expense_type_id' => ['value' => $salaries->id],
        ]);

        $this->assertSame([(int) $salaries->id], $ids);
    }

    public function test_filtered_expense_type_label_joins_selected_names(): void
    {
        $rent = ExpenseType::query()->create(['name' => 'Rent', 'is_active' => true]);
        $utilities = ExpenseType::query()->create(['name' => 'Utilities', 'is_active' => true]);

        $label = ExpenseExporter::filteredExpenseTypeLabel([
            'expense_type_id' => ['values' => [$utilities->id, $rent->id]],
        ]);

        $this->assertSame('Rent, Utilities', $label);
    }
}
