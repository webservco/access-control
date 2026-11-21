<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Contract;

/**
 * Roles and feature flags per user. A key is 1 to 64 lowercase letters, digits, "_", "." or "-"; a user ID is 1 to
 * 64 characters, not ending with a space. Anything else is refused with an `InvalidArgumentException`.
 *
 * Each method runs single statements, so it can be part of the caller's transaction.
 */
interface AccessServiceInterface
{
    /**
     * Deletes every role and feature flag of the user. Whether there was any.
     */
    public function deleteUser(string $userId): bool;

    /**
     * @return list<string> the users with the feature flag, in binary order
     */
    public function fetchUserIdsWithFeature(string $featureKey): array;

    /**
     * @return list<string> the users with the role, in binary order
     */
    public function fetchUserIdsWithRole(string $roleKey): array;

    /**
     * False when the user had the feature flag already.
     */
    public function grantFeature(string $userId, string $featureKey): bool;

    /**
     * False when the user had the role already.
     */
    public function grantRole(string $userId, string $roleKey): bool;

    public function hasFeature(string $userId, string $featureKey): bool;

    public function hasRole(string $userId, string $roleKey): bool;

    /**
     * False when the user didn't have the feature flag.
     */
    public function revokeFeature(string $userId, string $featureKey): bool;

    /**
     * False when the user didn't have the role.
     */
    public function revokeRole(string $userId, string $roleKey): bool;
}
