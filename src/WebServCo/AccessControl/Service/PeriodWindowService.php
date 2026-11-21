<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Service;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use WebServCo\AccessControl\Model\Period;

/**
 * The fixed UTC windows of each period. A time in another time zone is converted first.
 */
final readonly class PeriodWindowService
{
    /**
     * When the window that holds $dateTime resets: the start of the next one.
     */
    public function getNextWindowStart(Period $period, DateTimeImmutable $dateTime): DateTimeImmutable
    {
        return $this->getWindowStart($period, $dateTime)->add(
            new DateInterval(
                match ($period) {
                    Period::Day => 'P1D',
                    Period::Month => 'P1M',
                    Period::Week => 'P7D',
                },
            ),
        );
    }

    public function getWindowStart(Period $period, DateTimeImmutable $dateTime): DateTimeImmutable
    {
        $day = $dateTime->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);

        return match ($period) {
            Period::Day => $day,
            Period::Month => $day->setDate((int) $day->format('Y'), (int) $day->format('n'), 1),
            // The ISO 8601 year and week: the Monday can be in the year before.
            Period::Week => $day->setISODate((int) $day->format('o'), (int) $day->format('W')),
        };
    }
}
