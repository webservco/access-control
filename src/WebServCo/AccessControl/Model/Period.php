<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Model;

/**
 * Fixed UTC windows. A window resets when the next one starts. The order of the cases is the order in which
 * `consume()` counts.
 */
enum Period: string
{
    // From 00:00.
    case Day = 'day';

    // From Monday at 00:00 (ISO 8601).
    case Week = 'week';

    // From the 1st at 00:00.
    case Month = 'month';
}
