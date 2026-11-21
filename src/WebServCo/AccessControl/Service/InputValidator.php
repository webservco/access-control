<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service;

use InvalidArgumentException;

use function preg_match;

/**
 * The arguments of the services, before they reach the database.
 */
final readonly class InputValidator
{
    // INT UNSIGNED, the column of the maximums.
    public const int MAX_VALUE = 4_294_967_295;

    public function assertAmount(int $amount): void
    {
        if ($amount < 1 || $amount > self::MAX_VALUE) {
            throw new InvalidArgumentException('The amount must be from 1 to 4294967295.');
        }
    }

    /**
     * A role, a feature flag, an access level or a limit.
     */
    public function assertKey(string $key): void
    {
        if (preg_match('/^[a-z0-9_.-]{1,64}\z/', $key) !== 1) {
            throw new InvalidArgumentException('A key must be 1 to 64 lowercase letters, digits, "_", "." or "-".');
        }
    }

    public function assertMaxValue(int $maxValue): void
    {
        if ($maxValue < 0 || $maxValue > self::MAX_VALUE) {
            throw new InvalidArgumentException('The maximum must be from 0 to 4294967295.');
        }
    }

    /**
     * The columns are VARCHAR(64), so a longer ID could be truncated into another user's. A trailing space would be
     * ignored by MySQL's comparisons (PAD SPACE collations), so "1" and "1 " would be the same user there and two
     * users in SQLite. Invalid UTF-8 doesn't match the pattern.
     */
    public function assertUserId(string $userId): void
    {
        if (preg_match('/^.{1,64}(?<! )\z/su', $userId) !== 1) {
            throw new InvalidArgumentException('The user ID must be 1 to 64 characters, not ending with a space.');
        }
    }
}
