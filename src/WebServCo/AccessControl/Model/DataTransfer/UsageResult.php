<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Model\DataTransfer;

use WebServCo\Data\Contract\Transfer\DataTransferInterface;

/**
 * The answer of `check()` and `consume()`. The deciding period explains it: after a refusal, the full period that
 * resets last, because the user can go on only then; otherwise the period with the least left.
 */
final readonly class UsageResult implements DataTransferInterface
{
    /**
     * $periodUsages: every period of the limit, in the order of `Period`.
     *
     * @param non-empty-list<\WebServCo\AccessControl\Model\DataTransfer\PeriodUsage> $periodUsages
     */
    public function __construct(
        public bool $isAllowed,
        public PeriodUsage $decidingPeriodUsage,
        public array $periodUsages,
    ) {
    }
}
