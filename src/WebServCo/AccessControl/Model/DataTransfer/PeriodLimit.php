<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Model\DataTransfer;

use WebServCo\AccessControl\Model\Period;
use WebServCo\Data\Contract\Transfer\DataTransferInterface;

/**
 * One period of a user's limit: from the level, or from the user's override.
 */
final readonly class PeriodLimit implements DataTransferInterface
{
    public function __construct(public Period $period, public int $maxValue,)
    {
    }
}
