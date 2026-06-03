<?php

namespace App\Filament\Concerns;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait ConfiguresBranchSelect
{
    /**
     * @return \Closure(Builder): void
     */
    protected static function branchRelationshipQuery(?User $user = null): \Closure
    {
        return function (Builder $query) use ($user): void {
            $user ??= auth()->user();
            $query->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name');

            if ($user?->isBranchRestricted()) {
                $query->whereIn('id', $user->branchIds());
            }
        };
    }

    protected static function isBranchSelectLocked(): bool
    {
        return auth()->user()?->isLockedToSingleBranch() ?? false;
    }

    protected static function defaultBranchIdForSelect(): ?int
    {
        $user = auth()->user();

        if ($user) {
            $id = $user->defaultBranchIdForForms();
            if ($id) {
                return $id;
            }
        }

        return Branch::getDefaultBranch()?->id;
    }

    /**
     * @return array<int, string>
     */
    protected static function branchSelectOptions(): array
    {
        $query = Branch::query()->where('is_active', true);
        $user = auth()->user();

        if ($user?->isBranchRestricted()) {
            $query->whereIn('id', $user->branchIds());
        }

        return $query->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return list<int>
     */
    protected static function defaultBranchesForMultiSelect(): array
    {
        $user = auth()->user();

        if ($user?->isBranchRestricted()) {
            return $user->branchIds();
        }

        $default = Branch::getDefaultBranch()?->id;

        return $default ? [(int) $default] : [];
    }
}
