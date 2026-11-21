<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Contract;

interface UsageLimitServiceFactoryInterface
{
    /**
     * $databaseName: for an application that names the database in its queries. Null uses the connection's.
     */
    public function createUsageLimitService(?string $databaseName = null): UsageLimitServiceInterface;
}
