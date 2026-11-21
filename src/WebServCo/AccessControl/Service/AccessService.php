<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service;

use Override;
use Psr\Clock\ClockInterface;
use WebServCo\AccessControl\Contract\AccessServiceInterface;
use WebServCo\AccessControl\Contract\Storage\UserGrantStorageInterface;

final readonly class AccessService implements AccessServiceInterface
{
    public function __construct(
        private ClockInterface $clock,
        private UserGrantStorageInterface $featureStorage,
        private InputValidator $inputValidator,
        private UserGrantStorageInterface $roleStorage,
    ) {
    }

    #[Override]
    public function deleteUser(string $userId): bool
    {
        $this->inputValidator->assertUserId($userId);
        $deletedRoles = $this->roleStorage->deleteUser($userId);
        $deletedFeatures = $this->featureStorage->deleteUser($userId);

        return $deletedRoles || $deletedFeatures;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function fetchUserIdsWithFeature(string $featureKey): array
    {
        $this->inputValidator->assertKey($featureKey);

        return $this->featureStorage->fetchUserIds($featureKey);
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function fetchUserIdsWithRole(string $roleKey): array
    {
        $this->inputValidator->assertKey($roleKey);

        return $this->roleStorage->fetchUserIds($roleKey);
    }

    #[Override]
    public function grantFeature(string $userId, string $featureKey): bool
    {
        $this->assertGrant($userId, $featureKey);

        return $this->featureStorage->insert($userId, $featureKey, $this->clock->now());
    }

    #[Override]
    public function grantRole(string $userId, string $roleKey): bool
    {
        $this->assertGrant($userId, $roleKey);

        return $this->roleStorage->insert($userId, $roleKey, $this->clock->now());
    }

    #[Override]
    public function hasFeature(string $userId, string $featureKey): bool
    {
        $this->assertGrant($userId, $featureKey);

        return $this->featureStorage->has($userId, $featureKey);
    }

    #[Override]
    public function hasRole(string $userId, string $roleKey): bool
    {
        $this->assertGrant($userId, $roleKey);

        return $this->roleStorage->has($userId, $roleKey);
    }

    #[Override]
    public function revokeFeature(string $userId, string $featureKey): bool
    {
        $this->assertGrant($userId, $featureKey);

        return $this->featureStorage->delete($userId, $featureKey);
    }

    #[Override]
    public function revokeRole(string $userId, string $roleKey): bool
    {
        $this->assertGrant($userId, $roleKey);

        return $this->roleStorage->delete($userId, $roleKey);
    }

    private function assertGrant(string $userId, string $key): void
    {
        $this->inputValidator->assertUserId($userId);
        $this->inputValidator->assertKey($key);
    }
}
