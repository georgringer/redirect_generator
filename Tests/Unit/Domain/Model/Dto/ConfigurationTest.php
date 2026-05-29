<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Tests\Unit\Domain\Model\Dto;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $config = new Configuration();

        self::assertSame(307, $config->targetStatusCode);
        self::assertFalse($config->keepQueryParameters);
        self::assertFalse($config->forceHttps);
        self::assertFalse($config->isRegexp);
        self::assertFalse($config->respectQueryParameters);
        self::assertFalse($config->disableHitCount);
    }

    #[Test]
    public function customValues(): void
    {
        $config = new Configuration(
            targetStatusCode: 301,
            keepQueryParameters: true,
            forceHttps: true,
            isRegexp: true,
            respectQueryParameters: true,
            disableHitCount: true,
        );

        self::assertSame(301, $config->targetStatusCode);
        self::assertTrue($config->keepQueryParameters);
        self::assertTrue($config->forceHttps);
        self::assertTrue($config->isRegexp);
        self::assertTrue($config->respectQueryParameters);
        self::assertTrue($config->disableHitCount);
    }

    #[Test]
    #[DataProvider('validStatusCodes')]
    public function statusCodeIsAllowedReturnsTrueForValidCodes(int $statusCode): void
    {
        self::assertTrue(Configuration::statusCodeIsAllowed($statusCode));
    }

    public static function validStatusCodes(): array
    {
        return [[301], [302], [303], [307]];
    }

    #[Test]
    #[DataProvider('invalidStatusCodes')]
    public function statusCodeIsAllowedReturnsFalseForInvalidCodes(int $statusCode): void
    {
        self::assertFalse(Configuration::statusCodeIsAllowed($statusCode));
    }

    public static function invalidStatusCodes(): array
    {
        return [[200], [304], [308], [404], [500], [0]];
    }
}
