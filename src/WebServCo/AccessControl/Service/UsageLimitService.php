<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service;

use DateTimeImmutable;
use OutOfBoundsException;
use Override;
use Psr\Clock\ClockInterface;
use WebServCo\AccessControl\Contract\Storage\LimitStorageInterface;
use WebServCo\AccessControl\Contract\UsageLimitServiceInterface;
use WebServCo\AccessControl\Model\DataTransfer\PeriodLimit;
use WebServCo\AccessControl\Model\DataTransfer\UsageResult;
use WebServCo\AccessControl\Model\Period;

use function array_key_exists;
use function array_replace;
use function sprintf;

final readonly class UsageLimitService implements UsageLimitServiceInterface
{
    public function __construct(
        private ClockInterface $clock,
        private InputValidator $inputValidator,
        private LimitStorageInterface $limitStorage,
        private UsageCounterService $usageCounterService,
    ) {
    }

    #[Override]
    public function check(string $userId, string $limitKey, int $amount = 1): UsageResult
    {
        $this->assertUsage($userId, $limitKey, $amount);

        return $this->usageCounterService->check(
            $userId,
            $limitKey,
            $this->fetchPeriodLimits($userId, $limitKey),
            $amount,
            $this->clock->now(),
        );
    }

    #[Override]
    public function consume(string $userId, string $limitKey, int $amount = 1): UsageResult
    {
        $this->assertUsage($userId, $limitKey, $amount);

        return $this->usageCounterService->consume(
            $userId,
            $limitKey,
            $this->fetchPeriodLimits($userId, $limitKey),
            $amount,
            $this->clock->now(),
        );
    }

    #[Override]
    public function deleteCountersBefore(DateTimeImmutable $date): int
    {
        return $this->usageCounterService->deleteCountersBefore($date, $this->clock->now());
    }

    #[Override]
    public function deleteUser(string $userId): bool
    {
        $this->inputValidator->assertUserId($userId);
        $deletedLimits = $this->limitStorage->deleteUser($userId);
        $deletedCounters = $this->usageCounterService->deleteUser($userId);

        return $deletedLimits || $deletedCounters;
    }

    #[Override]
    public function setUserLevel(string $userId, string $levelKey): bool
    {
        $this->inputValidator->assertUserId($userId);
        $this->inputValidator->assertKey($levelKey);

        if (!$this->limitStorage->levelExists($levelKey)) {
            throw new OutOfBoundsException(sprintf('No access level "%s".', $levelKey));
        }

        return $this->limitStorage->setUserLevel($userId, $levelKey);
    }

    #[Override]
    public function setUserLimit(string $userId, string $limitKey, Period $period, ?int $maxValue): bool
    {
        $this->inputValidator->assertUserId($userId);
        $this->inputValidator->assertKey($limitKey);

        if ($maxValue === null) {
            return $this->limitStorage->deleteUserLimit($userId, $limitKey, $period);
        }

        $this->inputValidator->assertMaxValue($maxValue);

        return $this->limitStorage->setUserLimit($userId, $limitKey, $period, $maxValue);
    }

    private function assertUsage(string $userId, string $limitKey, int $amount): void
    {
        $this->inputValidator->assertUserId($userId);
        $this->inputValidator->assertKey($limitKey);
        $this->inputValidator->assertAmount($amount);
    }

    /**
     * Each period of the limit from the user's level, with the user's overrides on top.
     *
     * @return non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodLimit> in the order of `Period`
     */
    private function fetchPeriodLimits(string $userId, string $limitKey): array
    {
        $maxValues = array_replace(
            $this->limitStorage->fetchLevelLimit($userId, $limitKey),
            $this->limitStorage->fetchUserLimit($userId, $limitKey),
        );
        $periodLimits = [];

        foreach (Period::cases() as $period) {
            if (!array_key_exists($period->value, $maxValues)) {
                continue;
            }

            $periodLimits[] = new PeriodLimit($period, $maxValues[$period->value]);
        }

        if ($periodLimits === []) {
            // A forgotten limit or a mistyped key shows at once, instead of letting everything through.
            throw new OutOfBoundsException(
                sprintf('No limit "%s" for the user: none for the level, and no override.', $limitKey),
            );
        }

        return $periodLimits;
    }
}
