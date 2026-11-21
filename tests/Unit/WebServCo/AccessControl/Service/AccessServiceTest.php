<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Assets\FixedClock;
use Tests\Unit\Assets\SqlitePDOContainer;
use WebServCo\AccessControl\Model\GrantType;
use WebServCo\AccessControl\Service\AccessService;
use WebServCo\AccessControl\Service\InputValidator;
use WebServCo\AccessControl\Service\Storage\AbstractPDOStorage;
use WebServCo\AccessControl\Service\Storage\PDOUserGrantStorage;
use WebServCo\Data\Factory\Extraction\DataExtractionContainerFactory;

use function array_column;

#[CoversClass(AccessService::class)]
#[UsesClass(AbstractPDOStorage::class)]
#[UsesClass(InputValidator::class)]
#[UsesClass(PDOUserGrantStorage::class)]
final class AccessServiceTest extends TestCase
{
    public function testARoleIsGrantedAndRevoked(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        self::assertFalse($service->hasRole('1', 'admin'));
        self::assertTrue($service->grantRole('1', 'admin'));
        self::assertFalse($service->grantRole('1', 'admin'));
        self::assertTrue($service->hasRole('1', 'admin'));
        self::assertFalse($service->hasFeature('1', 'admin'));
        self::assertSame(['1'], $service->fetchUserIdsWithRole('admin'));
        self::assertSame([], $service->fetchUserIdsWithFeature('admin'));

        self::assertTrue($service->revokeRole('1', 'admin'));
        self::assertFalse($service->revokeRole('1', 'admin'));
        self::assertFalse($service->hasRole('1', 'admin'));
    }

    public function testAFeatureFlagIsGrantedAndRevoked(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        self::assertTrue($service->grantFeature('2', 'v3_access'));
        self::assertFalse($service->grantFeature('2', 'v3_access'));
        self::assertTrue($service->grantFeature('10', 'v3_access'));
        self::assertTrue($service->hasFeature('2', 'v3_access'));
        self::assertFalse($service->hasRole('2', 'v3_access'));
        self::assertSame(['10', '2'], $service->fetchUserIdsWithFeature('v3_access'));

        self::assertTrue($service->revokeFeature('2', 'v3_access'));
        self::assertFalse($service->revokeFeature('2', 'v3_access'));
        self::assertSame(['10'], $service->fetchUserIdsWithFeature('v3_access'));
    }

    public function testTheTimeOfAGrantIsStoredInUtc(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        $service->grantRole('1', 'admin');
        $service->grantFeature('1', 'v3_access');

        self::assertSame(
            ['2026-11-18 10:30:00'],
            array_column($pdoContainer->fetchRows('access_user_role'), 'when_added'),
        );
        self::assertSame(
            ['2026-11-18 10:30:00'],
            array_column($pdoContainer->fetchRows('access_user_feature'), 'when_added'),
        );
    }

    public function testDeletingAUserDeletesTheirRolesAndFeatureFlags(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        $service->grantRole('1', 'admin');
        $service->grantFeature('1', 'v3_access');
        $service->grantFeature('2', 'v3_access');

        self::assertTrue($service->deleteUser('1'));
        self::assertFalse($service->deleteUser('1'));
        self::assertFalse($service->hasRole('1', 'admin'));
        self::assertSame(['2'], $service->fetchUserIdsWithFeature('v3_access'));

        // Only a role, or only a feature flag.
        $service->grantRole('1', 'admin');
        self::assertTrue($service->deleteUser('1'));
        $service->grantFeature('1', 'v3_access');
        self::assertTrue($service->deleteUser('1'));
    }

    public function testAnInvalidKeyIsRefusedBeforeTheDatabase(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        $this->expectException(InvalidArgumentException::class);

        $service->grantRole('1', 'Admin');
    }

    public function testAnInvalidKeyIsRefusedInAList(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        $this->expectException(InvalidArgumentException::class);

        $service->fetchUserIdsWithRole('');
    }

    public function testAnInvalidFeatureKeyIsRefusedInAList(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        $this->expectException(InvalidArgumentException::class);

        $service->fetchUserIdsWithFeature('v3 access');
    }

    public function testAnInvalidUserIdIsRefused(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        $this->expectException(InvalidArgumentException::class);

        $service->hasFeature('', 'v3_access');
    }

    public function testAnInvalidUserIdIsRefusedOnDelete(): void
    {
        $pdoContainer = new SqlitePDOContainer();
        $service = $this->createService($pdoContainer);
        $this->expectException(InvalidArgumentException::class);

        $service->deleteUser('1 ');
    }

    private function createService(SqlitePDOContainer $pdoContainer): AccessService
    {
        $dataExtractionContainer = (new DataExtractionContainerFactory())->createDataExtractionContainer(false);

        return new AccessService(
            new FixedClock('2026-11-18 11:30:00 Europe/Budapest'),
            new PDOUserGrantStorage($dataExtractionContainer, $pdoContainer, GrantType::Feature),
            new InputValidator(),
            new PDOUserGrantStorage($dataExtractionContainer, $pdoContainer, GrantType::Role),
        );
    }
}
