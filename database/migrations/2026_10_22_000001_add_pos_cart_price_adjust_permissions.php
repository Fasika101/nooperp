<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $adjust = Permission::findOrCreate('AdjustPosCartPrices', $guard);
        $grant = Permission::findOrCreate('GrantPosCartPriceAdjust', $guard);

        $sales = Role::query()->where('name', 'sales')->where('guard_name', $guard)->first();
        if ($sales) {
            $sales->givePermissionTo($adjust);
        }

        // Managers may raise prices themselves, but cannot grant unlocks (super_admin only)
        $manager = Role::query()->where('name', 'manager')->where('guard_name', $guard)->first();
        if ($manager) {
            $manager->givePermissionTo($adjust);
            $manager->revokePermissionTo($grant);
        }

        $super = Role::query()->where('name', 'super_admin')->where('guard_name', $guard)->first();
        if ($super) {
            $super->givePermissionTo([$adjust, $grant]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', ['AdjustPosCartPrices', 'GrantPosCartPriceAdjust'])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
