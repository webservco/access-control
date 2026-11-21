<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Contract\Storage;

use WebServCo\AccessControl\Model\Period;

/**
 * Access levels, their limits, and each user's level and overrides.
 */
interface LimitStorageInterface
{
    /**
     * Deletes the user's level and overrides. Whether there was any.
     */
    public function deleteUser(string $userId): bool;

    /**
     * Whether the user had an override for the period.
     */
    public function deleteUserLimit(string $userId, string $limitKey, Period $period): bool;

    /**
     * The maximum of each period of the limit for the user's level, `default` when the user has none.
     *
     * @return array<string,int> by period value
     */
    public function fetchLevelLimit(string $userId, string $limitKey): array;

    /**
     * The user's overrides of the limit.
     *
     * @return array<string,int> by period value
     */
    public function fetchUserLimit(string $userId, string $limitKey): array;

    public function levelExists(string $levelKey): bool;

    public function setUserLevel(string $userId, string $levelKey): bool;

    public function setUserLimit(string $userId, string $limitKey, Period $period, int $maxValue): bool;
}
