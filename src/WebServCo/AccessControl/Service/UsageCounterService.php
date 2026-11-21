<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service;

use DateTimeImmutable;
use LogicException;
use Throwable;
use WebServCo\AccessControl\Contract\Storage\UsageCounterStorageInterface;
use WebServCo\AccessControl\Model\DataTransfer\PeriodLimit;
use WebServCo\AccessControl\Model\DataTransfer\PeriodUsage;
use WebServCo\AccessControl\Model\DataTransfer\UsageCounterKey;
use WebServCo\AccessControl\Model\DataTransfer\UsageResult;
use WebServCo\AccessControl\Model\Period;
use WebServCo\Database\Contract\TransactionalDatabaseServiceInterface;

use function array_map;

/**
 * The counters of `UsageLimitService`, in the windows that hold "now".
 *
 * Counting, in `consume()`: each counter is created with 0 before the transaction when it is missing. In the
 * transaction the amount is added to the counter of every period, in the order of `Period`, and each is read back.
 * When one is over its maximum, everything is rolled back. The first UPDATE locks the row until the end of the
 * transaction, so two requests can't both take the last unit, and the fixed order means two requests for the same
 * user and key wait for each other instead of deadlocking.
 */
final readonly class UsageCounterService
{
    public function __construct(
        private PeriodWindowService $periodWindowService,
        private TransactionalDatabaseServiceInterface $transactionalDatabaseService,
        private UsageCounterStorageInterface $usageCounterStorage,
        private UsageResultService $usageResultService,
    ) {
    }

    /**
     * @param non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodLimit> $periodLimits
     */
    public function check(
        string $userId,
        string $limitKey,
        array $periodLimits,
        int $amount,
        DateTimeImmutable $now,
    ): UsageResult {
        return $this->usageResultService->createCheckResult(
            array_map(
                function (PeriodLimit $periodLimit) use ($userId, $limitKey, $now): PeriodUsage {
                    return $this->usageResultService->createCurrentPeriodUsage(
                        $periodLimit,
                        $this->usageCounterStorage->fetchUsedValue(
                            $this->createCounterKey($userId, $limitKey, $periodLimit->period, $now),
                        ),
                        $now,
                    );
                },
                $periodLimits,
            ),
            $amount,
        );
    }

    /**
     * @param non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodLimit> $periodLimits
     */
    public function consume(
        string $userId,
        string $limitKey,
        array $periodLimits,
        int $amount,
        DateTimeImmutable $now,
    ): UsageResult {
        $this->assertNoTransaction();

        foreach ($periodLimits as $periodLimit) {
            $this->usageCounterStorage->ensureCounter(
                $this->createCounterKey($userId, $limitKey, $periodLimit->period, $now),
            );
        }

        $this->transactionalDatabaseService->beginTransaction();

        try {
            $result = $this->usageResultService->createConsumeResult(
                $this->addToCounters($userId, $limitKey, $periodLimits, $amount, $now),
                $amount,
            );
        } catch (Throwable $throwable) {
            $this->rollBackIfActive();

            throw $throwable;
        }

        if ($result->isAllowed) {
            $this->transactionalDatabaseService->commitTransaction();

            return $result;
        }

        $this->transactionalDatabaseService->rollBackTransaction();

        return $result;
    }

    /**
     * For each period, the counters of the windows before the one that holds $date, or $now when $date is later: a
     * date in the future would reach the running windows.
     */
    public function deleteCountersBefore(DateTimeImmutable $date, DateTimeImmutable $now): int
    {
        $until = $date->getTimestamp() < $now->getTimestamp()
            ? $date
            : $now;
        $deleted = 0;

        foreach (Period::cases() as $period) {
            $deleted += $this->usageCounterStorage->deleteCountersBefore(
                $period,
                $this->periodWindowService->getWindowStart($period, $until),
            );
        }

        return $deleted;
    }

    public function deleteUser(string $userId): bool
    {
        return $this->usageCounterStorage->deleteUser($userId);
    }

    /**
     * In the transaction: adds $amount to each counter and reads it back.
     *
     * @param non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodLimit> $periodLimits
     * @return non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodUsage> what was used before $amount
     */
    private function addToCounters(
        string $userId,
        string $limitKey,
        array $periodLimits,
        int $amount,
        DateTimeImmutable $now,
    ): array {
        return array_map(
            function (PeriodLimit $periodLimit) use ($userId, $limitKey, $amount, $now): PeriodUsage {
                return $this->usageResultService->createCurrentPeriodUsage(
                    $periodLimit,
                    $this->usageCounterStorage->addToCounter(
                        $this->createCounterKey($userId, $limitKey, $periodLimit->period, $now),
                        $amount,
                    ) - $amount,
                    $now,
                );
            },
            $periodLimits,
        );
    }

    /**
     * PDO has no nested transactions: committing or rolling back here would end the caller's.
     */
    private function assertNoTransaction(): void
    {
        if ($this->transactionalDatabaseService->getPDOContainer()->getPDO()->inTransaction()) {
            throw new LogicException('consume() runs its own transaction: call it outside of one.');
        }
    }

    private function createCounterKey(
        string $userId,
        string $limitKey,
        Period $period,
        DateTimeImmutable $now,
    ): UsageCounterKey {
        return new UsageCounterKey(
            $userId,
            $limitKey,
            $period,
            $this->periodWindowService->getWindowStart($period, $now),
        );
    }

    /**
     * InnoDB rolls the whole transaction back itself on a deadlock.
     */
    private function rollBackIfActive(): void
    {
        if (!$this->transactionalDatabaseService->getPDOContainer()->getPDO()->inTransaction()) {
            return;
        }

        $this->transactionalDatabaseService->rollBackTransaction();
    }
}
