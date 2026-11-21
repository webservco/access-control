<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Model\DataTransfer;

use DateTimeImmutable;
use WebServCo\AccessControl\Model\Period;
use WebServCo\Data\Contract\Transfer\DataTransferInterface;

/**
 * One period of a limit: its maximum, what is used in the current window, what is left, and when the window resets
 * (UTC).
 */
final readonly class PeriodUsage implements DataTransferInterface
{
    public function __construct(
        public Period $period,
        public int $maxValue,
        public int $usedValue,
        public int $leftValue,
        public DateTimeImmutable $resetsAt,
    ) {
    }
}
