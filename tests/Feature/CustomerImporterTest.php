<?php

namespace Tests\Feature;

use App\Filament\Imports\CustomerImporter;
use App\Models\Customer;
use App\Models\CustomerImportAudit;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_phone_match_only_renames_existing_customer(): void
    {
        $user = User::factory()->create();

        $import = Import::query()->create([
            'file_name' => 'customers.csv',
            'file_path' => 'customers.csv',
            'importer' => CustomerImporter::class,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Old Customer Name',
            'phone' => '+251911223344',
            'email' => 'old@example.com',
            'address' => 'Old Address',
            'tin' => 'TIN-OLD',
        ]);

        $importer = app(CustomerImporter::class, [
            'import' => $import,
            'columnMap' => [
                'name' => 'name',
                'phone' => 'phone',
                'email' => 'email',
                'address' => 'address',
                'tin' => 'tin',
            ],
            'options' => [],
        ]);

        $importer([
            'name' => 'New Imported Name',
            'phone' => '+251911223344',
            'email' => 'new@example.com',
            'address' => 'New Address',
            'tin' => 'TIN-NEW',
        ]);

        $customer->refresh();

        $this->assertSame('New Imported Name', $customer->name);
        $this->assertSame('old@example.com', $customer->email);
        $this->assertSame('Old Address', $customer->address);
        $this->assertSame('TIN-OLD', $customer->tin);

        $this->assertDatabaseHas('customer_import_audits', [
            'import_id' => $import->id,
            'customer_id' => $customer->id,
            'action' => CustomerImportAudit::ACTION_PHONE_NAME_REPLACED,
            'previous_name' => 'Old Customer Name',
            'current_name' => 'New Imported Name',
        ]);
    }
}
