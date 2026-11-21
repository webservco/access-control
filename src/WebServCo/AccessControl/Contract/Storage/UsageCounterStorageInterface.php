<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Contract\Storage;

use DateTimeImmutable;
use WebServCo\AccessControl\Model\DataTransfer\UsageCounterKey;
use WebServCo\AccessControl\Model\Period;

/**
 * One counter per user, limit key, period and window.
 */
interface UsageCounterStorageInterface
{
    /**
     * Adds $amount to the counter and returns the new value. The counter must exist (`ensureCounter()`).
     */
    public function addToCounter(UsageCounterKey $usageCounterKey, int $amount): int;

    /**
     * Deletes the counters of the period whose window starts before $windowStart. Returns how many.
     */
    public function deleteCountersBefore(Period $period, DateTimeImmutable $windowStart): int;

    /**
     * Deletes every counter of the user. Whether there was any.
     */
    public function deleteUser(string $userId): bool;

    /**
     * Creates the counter with 0 when it doesn't exist. Call it outside of a transaction: in InnoDB, the
     * duplicate-key error of an existing counter would keep a shared lock on its row until the end of the
     * transaction.
     */
    public function ensureCounter(UsageCounterKey $usageCounterKey): bool;

    /**
     * 0 when the counter doesn't exist.
     */
    public function fetchUsedValue(UsageCounterKey $usageCounterKey): int;
}
