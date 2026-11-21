<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebServCo\AccessControl\Model\Period;
use WebServCo\AccessControl\Service\PeriodWindowService;

/**
 * Each case: a time, the day its window starts and the day the next one starts, both at 00:00 UTC.
 */
#[CoversClass(PeriodWindowService::class)]
final class PeriodWindowServiceTest extends TestCase
{
    #[DataProvider('provideDays')]
    public function testTheDayAndItsReset(string $dateTime, string $windowStart, string $nextWindowStart): void
    {
        $this->assertWindow(Period::Day, $dateTime, $windowStart, $nextWindowStart);
    }

    #[DataProvider('provideMonths')]
    public function testTheMonthAndItsReset(string $dateTime, string $windowStart, string $nextWindowStart): void
    {
        $this->assertWindow(Period::Month, $dateTime, $windowStart, $nextWindowStart);
    }

    #[DataProvider('provideWeeks')]
    public function testTheWeekAndItsReset(string $dateTime, string $windowStart, string $nextWindowStart): void
    {
        $this->assertWindow(Period::Week, $dateTime, $windowStart, $nextWindowStart);
    }

    /**
     * @return iterable<string,array{string,string,string}>
     */
    public static function provideDays(): iterable
    {
        yield 'last second of a month' => ['2026-01-31 23:59:59 UTC', '2026-01-31', '2026-02-01'];
        yield 'leap day' => ['2028-02-29 12:00:00 UTC', '2028-02-29', '2028-03-01'];
        yield 'last second of a year' => ['2026-12-31 23:59:59 UTC', '2026-12-31', '2027-01-01'];

        // 00:30 in Budapest is 23:30 the day before in UTC.
        yield 'another time zone' => ['2026-11-23 00:30:00 +01:00', '2026-11-22', '2026-11-23'];
    }

    /**
     * @return iterable<string,array{string,string,string}>
     */
    public static function provideMonths(): iterable
    {
        yield 'last second of a month' => ['2026-01-31 23:59:59 UTC', '2026-01-01', '2026-02-01'];
        yield 'first second of a month' => ['2026-02-01 00:00:00 UTC', '2026-02-01', '2026-03-01'];
        yield 'leap day' => ['2028-02-29 12:00:00 UTC', '2028-02-01', '2028-03-01'];
        yield 'last second of a year' => ['2026-12-31 23:59:59 UTC', '2026-12-01', '2027-01-01'];
    }

    /**
     * @return iterable<string,array{string,string,string}>
     */
    public static function provideWeeks(): iterable
    {
        yield 'leap day, a Tuesday' => ['2028-02-29 12:00:00 UTC', '2028-02-28', '2028-03-06'];
        yield 'last second of a Sunday' => ['2026-11-22 23:59:59 UTC', '2026-11-16', '2026-11-23'];
        yield 'Monday at 00:00' => ['2026-11-23 00:00:00 UTC', '2026-11-23', '2026-11-30'];

        // 2027-01-01 is the Friday of ISO week 2026-W53.
        yield 'New Year in the last week of the year before' => ['2027-01-01 08:00:00 UTC', '2026-12-28', '2027-01-04'];

        // 2026-01-01 is the Thursday of ISO week 2026-W01, which starts in 2025.
        yield 'New Year in a week that starts the year before' => ['2026-01-01 00:00 UTC', '2025-12-29', '2026-01-05'];

        // 00:30 on Monday in Budapest is 23:30 on Sunday in UTC.
        yield 'another time zone' => ['2026-11-23 00:30:00 Europe/Budapest', '2026-11-16', '2026-11-23'];
    }

    private function assertWindow(Period $period, string $dateTime, string $windowStart, string $nextWindowStart): void
    {
        $periodWindowService = new PeriodWindowService();
        $date = new DateTimeImmutable($dateTime);

        self::assertSame(
            $windowStart . ' 00:00:00 UTC',
            $periodWindowService->getWindowStart($period, $date)->format('Y-m-d H:i:s e'),
        );
        self::assertSame(
            $nextWindowStart . ' 00:00:00 UTC',
            $periodWindowService->getNextWindowStart($period, $date)->format('Y-m-d H:i:s e'),
        );
    }
}
