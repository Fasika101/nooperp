<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ScopesFinanceDataByBranch
{
    protected function scopeExpenses(Builder $query): Builder
    {
        if (auth()->user()?->isBranchRestricted()) {
            $query->whereIn($query->qualifyColumn('branch_id'), auth()->user()->branchIds());
        }

        return $query;
    }

    protected function scopeOrders(Builder $query): Builder
    {
        if (auth()->user()?->isBranchRestricted()) {
            $query->whereIn($query->qualifyColumn('branch_id'), auth()->user()->branchIds());
        }

        return $query;
    }
}
