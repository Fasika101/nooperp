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
        $grant = Permission::findOrCreate('GrantPosCartPriceAdjust', $guard);

        $manager = Role::query()->where('name', 'manager')->where('guard_name', $guard)->first();
        if ($manager) {
            $manager->revokePermissionTo($grant);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $guard = config('auth.defaults.guard', 'web');
        $grant = Permission::findOrCreate('GrantPosCartPriceAdjust', $guard);

        $manager = Role::query()->where('name', 'manager')->where('guard_name', $guard)->first();
        if ($manager) {
            $manager->givePermissionTo($grant);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
