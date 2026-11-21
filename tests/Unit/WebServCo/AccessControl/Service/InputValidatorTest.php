<?php

declare(strict_types=1);

namespace Tests\Unit\WebServCo\AccessControl\Service;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebServCo\AccessControl\Service\InputValidator;

use function str_repeat;

#[CoversClass(InputValidator::class)]
final class InputValidatorTest extends TestCase
{
    public function testAnAmountOutOfRangeIsRefused(): void
    {
        $validator = new InputValidator();
        $validator->assertAmount(1);
        $validator->assertAmount(InputValidator::MAX_VALUE);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The amount must be from 1 to 4294967295.');

        $validator->assertAmount(0);
    }

    public function testAnAmountOverTheColumnIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InputValidator())->assertAmount(InputValidator::MAX_VALUE + 1);
    }

    #[DataProvider('provideInvalidKeys')]
    public function testAnInvalidKeyIsRefused(string $key): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('A key must be 1 to 64 lowercase letters, digits, "_", "." or "-".');

        (new InputValidator())->assertKey($key);
    }

    #[DataProvider('provideInvalidUserIds')]
    public function testAnInvalidUserIdIsRefused(string $userId): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The user ID must be 1 to 64 characters, not ending with a space.');

        (new InputValidator())->assertUserId($userId);
    }

    public function testAMaximumOutOfRangeIsRefused(): void
    {
        $validator = new InputValidator();
        $validator->assertMaxValue(0);
        $validator->assertMaxValue(InputValidator::MAX_VALUE);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The maximum must be from 0 to 4294967295.');

        $validator->assertMaxValue(-1);
    }

    public function testAMaximumOverTheColumnIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new InputValidator())->assertMaxValue(InputValidator::MAX_VALUE + 1);
    }

    #[DataProvider('provideValidKeys')]
    public function testAValidKeyPasses(string $key): void
    {
        $this->expectNotToPerformAssertions();

        (new InputValidator())->assertKey($key);
    }

    #[DataProvider('provideValidUserIds')]
    public function testAValidUserIdPasses(string $userId): void
    {
        $this->expectNotToPerformAssertions();

        (new InputValidator())->assertUserId($userId);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideInvalidKeys(): iterable
    {
        yield 'empty' => [''];
        yield '65 characters' => [str_repeat('a', 65)];
        yield 'uppercase' => ['Admin'];
        yield 'space' => ['v3 access'];
        yield 'colon' => ['api:own'];
        yield 'non-ASCII' => ['adminé'];
        yield 'line feed at the end' => ["admin\n"];
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideInvalidUserIds(): iterable
    {
        yield 'empty' => [''];
        yield '65 characters' => [str_repeat('1', 65)];
        yield '65 characters of two bytes' => [str_repeat('é', 65)];
        yield 'space at the end' => ['1 '];
        yield 'invalid UTF-8' => ["1\xC3"];
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideValidKeys(): iterable
    {
        yield 'one character' => ['a'];
        yield '64 characters' => [str_repeat('a', 64)];
        yield 'every allowed character' => ['api.own_data-2'];
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function provideValidUserIds(): iterable
    {
        yield 'digits' => ['1234567'];
        yield '64 characters' => [str_repeat('1', 64)];
        yield '64 characters of two bytes' => [str_repeat('é', 64)];
        yield 'space at the start and inside' => [' user 1'];
    }
}
