<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Contract;

interface AccessServiceFactoryInterface
{
    /**
     * $databaseName: for an application that names the database in its queries. Null uses the connection's.
     */
    public function createAccessService(?string $databaseName = null): AccessServiceInterface;
}
