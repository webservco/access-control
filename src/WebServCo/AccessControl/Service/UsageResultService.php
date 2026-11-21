<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service;

use DateTimeImmutable;
use WebServCo\AccessControl\Model\DataTransfer\PeriodLimit;
use WebServCo\AccessControl\Model\DataTransfer\PeriodUsage;
use WebServCo\AccessControl\Model\DataTransfer\UsageResult;

use function array_filter;
use function array_map;
use function array_values;
use function max;

/**
 * Whether an amount fits, and which period decides.
 */
final readonly class UsageResultService
{
    public function __construct(private PeriodWindowService $periodWindowService)
    {
    }

    /**
     * Whether $amount more fits in every period: the result of `check()`.
     *
     * @param non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodUsage> $periodUsages
     */
    public function createCheckResult(array $periodUsages, int $amount): UsageResult
    {
        $fullPeriodUsages = array_values(
            array_filter(
                $periodUsages,
                static function (PeriodUsage $periodUsage) use ($amount): bool {
                    return $periodUsage->usedValue + $amount > $periodUsage->maxValue;
                },
            ),
        );

        if ($fullPeriodUsages !== []) {
            return new UsageResult(false, $this->findLastToReset($fullPeriodUsages), $periodUsages);
        }

        return new UsageResult(true, $this->findLeastLeft($periodUsages), $periodUsages);
    }

    /**
     * The result of `consume()`: as `check()`'s, with $amount counted when it fits.
     *
     * $periodUsages: what was used before $amount.
     *
     * @param non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodUsage> $periodUsages
     */
    public function createConsumeResult(array $periodUsages, int $amount): UsageResult
    {
        $result = $this->createCheckResult($periodUsages, $amount);

        if (!$result->isAllowed) {
            return $result;
        }

        return $this->createCheckResult(
            array_map(
                function (PeriodUsage $periodUsage) use ($amount): PeriodUsage {
                    return $this->createPeriodUsage(
                        new PeriodLimit($periodUsage->period, $periodUsage->maxValue),
                        $periodUsage->usedValue + $amount,
                        $periodUsage->resetsAt,
                    );
                },
                $periodUsages,
            ),
            0,
        );
    }

    /**
     * The period in the window that holds $now.
     */
    public function createCurrentPeriodUsage(
        PeriodLimit $periodLimit,
        int $usedValue,
        DateTimeImmutable $now,
    ): PeriodUsage {
        return $this->createPeriodUsage(
            $periodLimit,
            $usedValue,
            $this->periodWindowService->getNextWindowStart($periodLimit->period, $now),
        );
    }

    private function createPeriodUsage(
        PeriodLimit $periodLimit,
        int $usedValue,
        DateTimeImmutable $resetsAt,
    ): PeriodUsage {
        return new PeriodUsage(
            $periodLimit->period,
            $periodLimit->maxValue,
            $usedValue,
            max(0, $periodLimit->maxValue - $usedValue),
            $resetsAt,
        );
    }

    /**
     * The least left; between equals, the one that resets last.
     *
     * @param non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodUsage> $periodUsages
     */
    private function findLeastLeft(array $periodUsages): PeriodUsage
    {
        $found = $periodUsages[0];

        foreach ($periodUsages as $periodUsage) {
            if (!$this->hasLessLeft($periodUsage, $found)) {
                continue;
            }

            $found = $periodUsage;
        }

        return $found;
    }

    /**
     * The one that resets last; between equals, the longer period (the later in the list).
     *
     * @param non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodUsage> $periodUsages
     */
    private function findLastToReset(array $periodUsages): PeriodUsage
    {
        $found = $periodUsages[0];

        foreach ($periodUsages as $periodUsage) {
            if ($periodUsage->resetsAt->getTimestamp() < $found->resetsAt->getTimestamp()) {
                continue;
            }

            $found = $periodUsage;
        }

        return $found;
    }

    /**
     * Less left than $found, or as much left and a reset no earlier.
     */
    private function hasLessLeft(PeriodUsage $periodUsage, PeriodUsage $found): bool
    {
        if ($periodUsage->leftValue !== $found->leftValue) {
            return $periodUsage->leftValue < $found->leftValue;
        }

        return $periodUsage->resetsAt->getTimestamp() >= $found->resetsAt->getTimestamp();
    }
}
