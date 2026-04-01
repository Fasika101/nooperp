<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Category;
use App\Models\CrmDeal;
use App\Models\CrmDealStage;
use App\Models\CrmLead;
use App\Models\CrmLeadStage;
use App\Models\CrmLeadTask;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\OrderItem;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectTaskStage;
use App\Models\StockPurchase;
use App\Models\TaxType;
use App\Models\TelegramBotChat;
use App\Models\TelegramBotMessage;
use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class DataWipeService
{
    /** @var list<string> */
    public const GROUP_ORDER = [
        'sales',
        'bank',
        'expenses',
        'inventory',
        'reference_types',
        'telegram_crm',
        'telegram_bot',
        'projects_crm',
        'users',
        'roles_permissions',
    ];

    /**
     * @param  list<string>  $groups
     */
    public function wipe(array $groups, int $exceptUserId): void
    {
        $ordered = array_values(array_intersect(self::GROUP_ORDER, $groups));

        DB::transaction(function () use ($ordered, $exceptUserId): void {
            foreach ($ordered as $group) {
                match ($group) {
                    'sales' => $this->wipeSales(),
                    'bank' => $this->wipeBank(),
                    'expenses' => $this->wipeExpenses(),
                    'inventory' => $this->wipeInventory(),
                    'reference_types' => $this->wipeReferenceTypes(),
                    'telegram_crm' => $this->wipeTelegramCrm(),
                    'telegram_bot' => $this->wipeTelegramBot(),
                    'projects_crm' => $this->wipeProjectsCrm(),
                    'users' => $this->wipeUsers($exceptUserId),
                    'roles_permissions' => $this->wipeRolesAndPermissions($exceptUserId),
                    default => null,
                };
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function wipeSales(): void
    {
        // Cascades orders → order_items, payments; prescriptions; etc.
        Customer::query()->delete();
    }

    private function wipeBank(): void
    {
        BankTransaction::query()->delete();
        BankAccount::query()->delete();
    }

    private function wipeExpenses(): void
    {
        Expense::query()->delete();
        ExpenseType::query()->delete();
    }

    private function wipeInventory(): void
    {
        StockPurchase::query()->delete();
        OrderItem::query()->delete();
        Product::query()->delete();
        Category::query()->delete();
    }

    private function wipeReferenceTypes(): void
    {
        TaxType::query()->delete();
        PaymentType::query()->delete();
    }

    private function wipeTelegramCrm(): void
    {
        TelegramMessage::query()->delete();
        TelegramChat::query()->delete();
    }

    private function wipeTelegramBot(): void
    {
        TelegramBotMessage::query()->delete();
        TelegramBotChat::query()->delete();
    }

    private function wipeProjectsCrm(): void
    {
        CrmLeadTask::query()->delete();
        CrmDeal::query()->delete();
        CrmLead::query()->delete();
        Project::query()->delete();
        CrmLeadStage::query()->delete();
        CrmDealStage::query()->delete();
        ProjectTaskStage::query()->delete();
    }

    private function wipeUsers(int $exceptUserId): void
    {
        $names = config('permission.table_names');
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');

        User::query()
            ->whereKeyNot($exceptUserId)
            ->each(function (User $user) use ($names, $morphKey): void {
                if (! empty($names)) {
                    DB::table($names['model_has_roles'])
                        ->where($morphKey, $user->getKey())
                        ->where('model_type', $user->getMorphClass())
                        ->delete();
                    DB::table($names['model_has_permissions'])
                        ->where($morphKey, $user->getKey())
                        ->where('model_type', $user->getMorphClass())
                        ->delete();
                }
                $user->delete();
            });
    }

    private function wipeRolesAndPermissions(int $preserveUserId): void
    {
        $names = config('permission.table_names');

        if (empty($names)) {
            return;
        }

        DB::table($names['role_has_permissions'])->delete();
        DB::table($names['model_has_roles'])->delete();
        DB::table($names['model_has_permissions'])->delete();
        DB::table($names['roles'])->delete();
        DB::table($names['permissions'])->delete();

        $guardName = (string) config('auth.defaults.guard', 'web');
        $role = Role::query()->firstOrCreate(
            ['name' => 'super_admin', 'guard_name' => $guardName],
        );

        User::query()->find($preserveUserId)?->assignRole($role);
    }
}
