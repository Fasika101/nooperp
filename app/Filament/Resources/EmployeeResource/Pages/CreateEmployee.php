<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Schemas\EmployeeFormSchema;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayrollTaxCalculator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CreateEmployee extends CreateRecord
{
    use HasWizard;

    protected static string $resource = EmployeeResource::class;

    protected function getSteps(): array
    {
        return EmployeeFormSchema::wizardSteps(Setting::getDefaultCurrency());
    }

    protected bool $shouldCreatePanelUser = false;

    protected ?string $newUserPassword = null;

    /** @var list<int|string> */
    protected array $newUserRoleIds = [];

    protected ?int $newUserBranchId = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $gross = isset($data['base_salary']) ? (float) $data['base_salary'] : 0;
        $computed = PayrollTaxCalculator::calculate($gross);
        $data['payroll_tax_amount'] = $computed['tax'];
        $data['net_salary_after_tax'] = $computed['net'];

        $this->shouldCreatePanelUser = (bool) ($data['create_panel_user'] ?? false);
        $this->newUserPassword = filled($data['new_user_password'] ?? null) ? (string) $data['new_user_password'] : null;
        $this->newUserRoleIds = isset($data['new_user_role_ids']) && is_array($data['new_user_role_ids'])
            ? array_values(array_filter($data['new_user_role_ids']))
            : [];
        $this->newUserBranchId = isset($data['new_user_branch_id']) ? (int) $data['new_user_branch_id'] : null;

        unset(
            $data['create_panel_user'],
            $data['new_user_password'],
            $data['new_user_role_ids'],
            $data['new_user_branch_id'],
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! $this->shouldCreatePanelUser) {
            return;
        }

        $employee = $this->getRecord();

        if (! filled($employee->email)) {
            Notification::make()
                ->danger()
                ->title('Panel user not created')
                ->body('Set an email on the employee before creating a panel login.')
                ->send();

            return;
        }

        if (User::query()->where('email', $employee->email)->exists()) {
            Notification::make()
                ->danger()
                ->title('Panel user not created')
                ->body('A user with this email already exists. Link manually or use a different email.')
                ->send();

            return;
        }

        $plain = $this->newUserPassword ?? Str::password(16);

        $user = User::query()->create([
            'name' => $employee->full_name,
            'email' => $employee->email,
            'password' => Hash::make($plain),
            'branch_id' => $this->newUserBranchId ?: $employee->branch_id,
        ]);

        if ($this->newUserRoleIds !== []) {
            $roleNames = Role::query()->whereIn('id', $this->newUserRoleIds)->pluck('name')->all();
            $user->syncRoles($roleNames);
        }

        $employee->user_id = $user->id;
        $employee->saveQuietly();

        Notification::make()
            ->success()
            ->title('Panel user created')
            ->body('Temporary password: '.$plain.' — share securely and ask them to change it from their profile.')
            ->persistent()
            ->send();
    }
}
