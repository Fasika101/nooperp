<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Timed unlock so cashiers may raise (not lower) POS cart line prices.
 */
class PosCartPriceAdjustService
{
    public const PERMISSION_ADJUST = 'AdjustPosCartPrices';

    public const PERMISSION_GRANT = 'GrantPosCartPriceAdjust';

    protected static function cacheKey(int $userId): string
    {
        return 'pos_cart_price_adjust_until:'.$userId;
    }

    public function canGrant(?User $actor): bool
    {
        if (! $actor) {
            return false;
        }

        // Only super admin may unlock timed price raises for sellers
        return $actor->hasRole(User::ROLE_SUPER_ADMIN);
    }

    public function hasAdjustPermission(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasUnrestrictedBranchAccess()
            || $user->can(self::PERMISSION_ADJUST);
    }

    /**
     * Active unlock window for this user (managers always count as unlocked while they have adjust rights).
     */
    public function isUnlocked(?User $user): bool
    {
        if (! $this->hasAdjustPermission($user)) {
            return false;
        }

        // Managers / super-admins can always adjust without a timed grant
        if ($user->hasUnrestrictedBranchAccess()) {
            return true;
        }

        $until = $this->unlockExpiresAt($user->id);

        return $until !== null && $until->isFuture();
    }

    public function unlockExpiresAt(int $userId): ?Carbon
    {
        $ts = Cache::get(self::cacheKey($userId));
        if (! is_numeric($ts)) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $ts);
    }

    public function secondsRemaining(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        if ($user->hasUnrestrictedBranchAccess() && $this->hasAdjustPermission($user)) {
            return PHP_INT_MAX;
        }

        $until = $this->unlockExpiresAt($user->id);
        if (! $until || $until->isPast()) {
            return 0;
        }

        return max(0, $until->getTimestamp() - now()->getTimestamp());
    }

    /**
     * Grant (or extend) a timed unlock for a seller.
     */
    public function grant(User $target, int $minutes): Carbon
    {
        $minutes = max(1, min(240, $minutes));
        $until = now()->addMinutes($minutes);
        Cache::put(self::cacheKey($target->id), $until->getTimestamp(), $until);

        return $until;
    }

    public function revoke(int $userId): void
    {
        Cache::forget(self::cacheKey($userId));
    }
}
