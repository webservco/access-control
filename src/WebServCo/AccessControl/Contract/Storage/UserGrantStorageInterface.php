<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Contract\Storage;

use DateTimeImmutable;

/**
 * The keys granted to users, of one kind: roles or feature flags.
 */
interface UserGrantStorageInterface
{
    /**
     * Whether the user had the key.
     */
    public function delete(string $userId, string $key): bool;

    /**
     * Deletes every key of the user. Whether there was any.
     */
    public function deleteUser(string $userId): bool;

    /**
     * @return list<string> in binary order
     */
    public function fetchUserIds(string $key): array;

    public function has(string $userId, string $key): bool;

    /**
     * False when the user has the key already.
     */
    public function insert(string $userId, string $key, DateTimeImmutable $whenAdded): bool;
}
