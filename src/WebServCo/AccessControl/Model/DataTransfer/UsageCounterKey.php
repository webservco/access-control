<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Model\DataTransfer;

use DateTimeImmutable;
use WebServCo\AccessControl\Model\Period;
use WebServCo\Data\Contract\Transfer\DataTransferInterface;

/**
 * The counter of one user, limit key, period and window.
 */
final readonly class UsageCounterKey implements DataTransferInterface
{
    public function __construct(
        public string $userId,
        public string $limitKey,
        public Period $period,
        public DateTimeImmutable $windowStart,
    ) {
    }
}
