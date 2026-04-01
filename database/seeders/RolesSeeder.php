<?php

namespace Database\Seeders;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * Seed Filament Shield permissions and default roles.
     *
     * 1. Generates all permission records from resources/pages/widgets (same as `shield:generate`).
     * 2. Creates/syncs roles and attaches permissions.
     *
     * Run: php artisan db:seed --class=RolesSeeder
     */
    public function run(): void
    {
        $this->generateShieldPermissions();

        $guard = Utils::getFilamentAuthGuard();

        $this->syncSuperAdminRole($guard);
        $this->syncPanelUserRole($guard);

        $this->syncRole('sales', $this->salesPermissionNames(), $guard);
        $this->syncRole('inventory', $this->inventoryPermissionNames(), $guard);
        $this->syncRole('optical', $this->opticalPermissionNames(), $guard);
        $this->syncRole('finance', $this->financePermissionNames(), $guard);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function generateShieldPermissions(): void
    {
        $exitCode = Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
            '--no-interaction' => true,
        ]);

        if ($exitCode !== 0) {
            $this->command->warn('shield:generate returned a non-zero exit code. Check permissions manually.');
        } else {
            $this->command->info(Artisan::output());
        }
    }

    protected function syncSuperAdminRole(string $guard): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => Utils::getSuperAdminName(), 'guard_name' => $guard],
        );

        $role->syncPermissions(Permission::query()->get());

        $this->command->info('Role ['.$role->name.'] synced with all permissions.');
    }

    protected function syncPanelUserRole(string $guard): void
    {
        if (! Utils::isPanelUserRoleEnabled()) {
            return;
        }

        $role = Role::query()->firstOrCreate(
            ['name' => Utils::getPanelUserRoleName(), 'guard_name' => $guard],
        );

        $names = $this->panelUserPermissionNames();
        $role->syncPermissions($this->permissionsByNames($names));

        $this->command->info('Role ['.$role->name.'] synced with '.count($names).' permissions.');
    }

    /**
     * @param  list<string>  $permissionNames
     */
    protected function syncRole(string $roleName, array $permissionNames, string $guard): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard_name' => $guard],
        );

        $permissions = $this->permissionsByNames($permissionNames);
        $role->syncPermissions($permissions);

        $missing = array_diff($permissionNames, $permissions->pluck('name')->all());
        if ($missing !== []) {
            $this->command->warn("Role [{$roleName}]: missing permissions skipped: ".implode(', ', $missing));
        }

        $this->command->info("Role [{$roleName}] synced ({$permissions->count()} permissions).");
    }

    /**
     * @param  list<string>  $names
     * @return Collection<int, Permission>
     */
    protected function permissionsByNames(array $names)
    {
        return Permission::query()->whereIn('name', $names)->get();
    }

    /**
     * Minimal access: dashboard widgets + read-only sales entry points.
     *
     * @return list<string>
     */
    protected function panelUserPermissionNames(): array
    {
        return [
            'View:Dashboard',
            'View:LibaStatsOverviewWidget',
            'View:RevenueChartWidget',
            'View:SalesChartWidget',
            'ViewAny:Order',
            'View:Order',
            'ViewAny:Customer',
            'View:Customer',
        ];
    }

    /**
     * @return list<string>
     */
    protected function salesPermissionNames(): array
    {
        $resources = ['Order', 'OrderItem', 'Customer', 'Payment', 'PaymentType'];
        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete', 'Replicate', 'Reorder', 'RestoreAny', 'ForceDeleteAny'];

        $perms = $this->matrixPermissions($resources, $actions);

        return array_merge($perms, [
            'View:Dashboard',
            'View:PosPage',
            'View:LibaStatsOverviewWidget',
            'View:RevenueChartWidget',
            'View:SalesChartWidget',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function inventoryPermissionNames(): array
    {
        $resources = ['Product', 'Category', 'StockPurchase'];
        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete', 'Replicate', 'Reorder', 'RestoreAny', 'ForceDeleteAny'];

        $perms = $this->matrixPermissions($resources, $actions);

        return array_merge($perms, [
            'View:Dashboard',
            'View:LibaStatsOverviewWidget',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function opticalPermissionNames(): array
    {
        $resources = ['Prescription'];
        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete', 'Replicate', 'Reorder', 'RestoreAny', 'ForceDeleteAny'];

        $perms = $this->matrixPermissions($resources, $actions);

        return array_merge($perms, [
            'View:Dashboard',
            'View:LibaStatsOverviewWidget',
        ]);
    }

    /**
     * @return list<string>
     */
    protected function financePermissionNames(): array
    {
        $resources = ['BankAccount', 'BankTransaction', 'Expense', 'ExpenseType', 'TaxType'];
        $actions = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'Restore', 'ForceDelete', 'Replicate', 'Reorder', 'RestoreAny', 'ForceDeleteAny'];

        $perms = $this->matrixPermissions($resources, $actions);

        return array_merge($perms, [
            'View:Dashboard',
            'View:FinancePage',
            'View:ProfitLossReportPage',
            'View:SettingsPage',
            'View:FinanceStatsWidget',
            'View:BankAccountOverviewWidget',
            'View:BankAccountReconciliationWidget',
            'View:RecurringExpensesDueWidget',
            'View:LibaStatsOverviewWidget',
            'View:RevenueChartWidget',
        ]);
    }

    /**
     * @param  list<string>  $resources
     * @param  list<string>  $actions
     * @return list<string>
     */
    protected function matrixPermissions(array $resources, array $actions): array
    {
        $perms = [];
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $perms[] = "{$action}:{$resource}";
            }
        }

        return $perms;
    }
}
