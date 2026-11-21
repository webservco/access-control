<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    private DateTimeImmutable $now;

    /**
     * @param string $now any format DateTimeImmutable reads; without a time zone, the container's (not UTC)
     */
    public function __construct(string $now)
    {
        $this->now = new DateTimeImmutable($now);
    }

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function setNow(string $now): void
    {
        $this->now = new DateTimeImmutable($now);
    }
}
