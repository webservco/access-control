<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Contract;

use DateTimeImmutable;
use WebServCo\AccessControl\Model\DataTransfer\UsageResult;
use WebServCo\AccessControl\Model\Period;

/**
 * Usage limits per user, in fixed UTC windows (`Period`). A user's limit is each period of the limit key from the
 * user's access level (`default` without one), with the user's overrides on top. A maximum of 0 blocks.
 *
 * Keys and user IDs as in `AccessServiceInterface`. An amount is 1 to 4294967295 and a maximum 0 to 4294967295;
 * anything else is refused with an `InvalidArgumentException`.
 */
interface UsageLimitServiceInterface
{
    /**
     * Whether $amount more fits in every period of the limit, without counting it.
     *
     * @throws \OutOfBoundsException when the limit key has no period for the user (none for the level, no override)
     */
    public function check(string $userId, string $limitKey, int $amount = 1): UsageResult;

    /**
     * Counts $amount only when every period of the limit has room for it; `isAllowed` says whether it did.
     *
     * It runs its own transaction, so it can't be called inside one of the caller's (`LogicException`).
     *
     * @throws \OutOfBoundsException when the limit key has no period for the user (none for the level, no override)
     */
    public function consume(string $userId, string $limitKey, int $amount = 1): UsageResult;

    /**
     * Deletes the counters of the windows that ended by $date: for each period, those of the windows before the one
     * that holds $date. A running window is never deleted, even for a date in the future. Returns how many.
     */
    public function deleteCountersBefore(DateTimeImmutable $date): int;

    /**
     * Deletes the user's level, overrides and counters. Whether there was any.
     */
    public function deleteUser(string $userId): bool;

    /**
     * @throws \OutOfBoundsException when the level doesn't exist
     */
    public function setUserLevel(string $userId, string $levelKey): bool;

    /**
     * For this user, replaces the level's maximum for the period, or adds the period. Null removes the override;
     * then false when there was none.
     */
    public function setUserLimit(string $userId, string $limitKey, Period $period, ?int $maxValue): bool;
}
