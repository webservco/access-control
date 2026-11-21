<?php

declare(strict_types=1);

namespace WebServCo\AccessControl\Factory;

use Override;
use WebServCo\AccessControl\Contract\AccessServiceFactoryInterface;
use WebServCo\AccessControl\Contract\AccessServiceInterface;
use WebServCo\AccessControl\Model\GrantType;
use WebServCo\AccessControl\Service\AccessService;
use WebServCo\AccessControl\Service\InputValidator;
use WebServCo\AccessControl\Service\Storage\PDOUserGrantStorage;
use WebServCo\AccessControl\Service\SystemClock;
use WebServCo\Data\Contract\Extraction\DataExtractionContainerInterface;
use WebServCo\Database\Contract\PDOContainerInterface;

/**
 * The tables are `access_user_role` and `access_user_feature` (`resources/access-control.sql`).
 */
final readonly class AccessServiceFactory implements AccessServiceFactoryInterface
{
    public function __construct(
        private DataExtractionContainerInterface $dataExtractionContainer,
        private PDOContainerInterface $pdoContainer,
    ) {
    }

    #[Override]
    public function createAccessService(?string $databaseName = null): AccessServiceInterface
    {
        return new AccessService(
            new SystemClock(),
            $this->createUserGrantStorage(GrantType::Feature, $databaseName),
            new InputValidator(),
            $this->createUserGrantStorage(GrantType::Role, $databaseName),
        );
    }

    private function createUserGrantStorage(GrantType $grantType, ?string $databaseName): PDOUserGrantStorage
    {
        return new PDOUserGrantStorage($this->dataExtractionContainer, $this->pdoContainer, $grantType, $databaseName);
    }
}
