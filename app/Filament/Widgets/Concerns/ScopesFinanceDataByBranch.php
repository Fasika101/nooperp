<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ScopesFinanceDataByBranch
{
    protected function scopeExpenses(Builder $query): Builder
    {
        if (auth()->user()?->isBranchRestricted()) {
            $query->where($query->qualifyColumn('branch_id'), auth()->user()->branch_id);
        }

        return $query;
    }

    protected function scopeOrders(Builder $query): Builder
    {
        if (auth()->user()?->isBranchRestricted()) {
            $query->where($query->qualifyColumn('branch_id'), auth()->user()->branch_id);
        }

        return $query;
    }
}
