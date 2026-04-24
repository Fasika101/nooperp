<?php

namespace Tests\Feature;

use App\Filament\Pages\DataWipePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DataWipePageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_data_wipe_page(): void
    {
        Role::query()->create([
            'name' => User::ROLE_SUPER_ADMIN,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole(User::ROLE_SUPER_ADMIN);

        $this->actingAs($user);

        $this->assertTrue(DataWipePage::canAccess());
    }

    public function test_manager_cannot_access_data_wipe_page(): void
    {
        Role::query()->create([
            'name' => User::ROLE_MANAGER,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole(User::ROLE_MANAGER);

        $this->actingAs($user);

        $this->assertFalse(DataWipePage::canAccess());
    }
}
