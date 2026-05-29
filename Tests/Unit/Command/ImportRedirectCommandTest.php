<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Tests\Unit\Command;

use GeorgRinger\RedirectGenerator\Command\ImportRedirectCommand;
use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use GeorgRinger\RedirectGenerator\Service\UrlMatcher;
use GeorgRinger\RedirectGenerator\Utility\NotificationHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class ImportRedirectCommandTest extends TestCase
{
    private ImportRedirectCommand $subject;

    protected function setUp(): void
    {
        $this->subject = new ImportRedirectCommand(
            $this->createMock(RedirectRepository::class),
            $this->createMock(UrlMatcher::class),
            $this->createMock(NotificationHandler::class),
            $this->createMock(ExtensionConfiguration::class),
        );
    }

    // --- resolveDelimiter ---

    #[Test]
    #[DataProvider('delimiterProvider')]
    public function resolveDelimiterReturnsCorrectCharacter(string $input, string $expected): void
    {
        $result = $this->callProtected('resolveDelimiter', $input);
        self::assertSame($expected, $result);
    }

    public static function delimiterProvider(): array
    {
        return [
            'semicolon by default' => [';', ';'],
            'comma'                => [',', ','],
            'tab keyword'          => ['tab', "\t"],
            'unknown falls back'   => ['|', ';'],
        ];
    }

    // --- targetEqualsSource ---

    #[Test]
    #[DataProvider('targetEqualsSourceProvider')]
    public function targetEqualsSourceDetectsSameUrl(string $target, string $source, bool $expected): void
    {
        $result = $this->callProtected('targetEqualsSource', $target, $source);
        self::assertSame($expected, $result);
    }

    public static function targetEqualsSourceProvider(): array
    {
        return [
            'identical paths'               => ['/foo', '/foo', true],
            'http vs https same host'       => ['http://example.com/path', 'https://example.com/path', true],
            'trailing slash difference'     => ['https://example.com/path/', 'https://example.com/path', true],
            'different paths'               => ['/foo', '/bar', false],
            'different hosts'               => ['https://a.com/p', 'https://b.com/p', false],
        ];
    }

    // --- isExternalDomain ---

    #[Test]
    public function isExternalDomainReturnsFalseWhenNoDomainsDefined(): void
    {
        self::assertFalse($this->callProtected('isExternalDomain', 'https://example.com/page'));
    }

    #[Test]
    public function isExternalDomainReturnsTrueForConfiguredDomain(): void
    {
        $this->callProtected('setExternalDomains', 'example.com,other.org');
        self::assertTrue($this->callProtected('isExternalDomain', 'https://example.com/page'));
    }

    #[Test]
    public function isExternalDomainReturnsFalseForUnknownDomain(): void
    {
        $this->callProtected('setExternalDomains', 'example.com');
        self::assertFalse($this->callProtected('isExternalDomain', 'https://unknown.com/page'));
    }

    // --- validateFilePath ---

    #[Test]
    public function validateFilePathThrowsForNonExistentFile(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionCode(1568544111);
        $this->callProtected('validateFilePath', '/tmp/nonexistent_file_xyz.csv');
    }

    #[Test]
    public function validateFilePathThrowsForNonCsvExtension(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'redir_') . '.txt';
        touch($tmpFile);

        try {
            $this->expectException(\UnexpectedValueException::class);
            $this->expectExceptionCode(1568544112);
            $this->callProtected('validateFilePath', $tmpFile);
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function validateFilePathDoesNotThrowForValidCsvFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'redir_') . '.csv';
        touch($tmpFile);

        try {
            $this->callProtected('validateFilePath', $tmpFile);
            $this->addToAssertionCount(1);
        } finally {
            unlink($tmpFile);
        }
    }

    // --- validateCsvHeaders ---

    #[Test]
    public function validateCsvHeadersThrowsWhenSourceIsMissing(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->callProtected('validateCsvHeaders', ['target' => 'https://example.com'], 0);
    }

    #[Test]
    public function validateCsvHeadersThrowsWhenTargetIsMissing(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->callProtected('validateCsvHeaders', ['source' => '/old'], 0);
    }

    #[Test]
    public function validateCsvHeadersThrowsWhenSourceIsEmpty(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->callProtected('validateCsvHeaders', ['source' => '', 'target' => '/new'], 1);
    }

    #[Test]
    public function validateCsvHeadersPassesForValidRow(): void
    {
        $this->callProtected('validateCsvHeaders', ['source' => '/old', 'target' => 'https://example.com'], 0);
        $this->addToAssertionCount(1);
    }

    // --- getConfigurationFromItem ---

    #[Test]
    public function getConfigurationFromItemUsesStatusCodeFromCsv(): void
    {
        /** @var Configuration $config */
        $config = $this->callProtected('getConfigurationFromItem', ['source' => '/a', 'target' => '/b', 'status_code' => '301']);
        self::assertSame(301, $config->targetStatusCode);
    }

    #[Test]
    public function getConfigurationFromItemDefaultsTo307ForInvalidStatusCode(): void
    {
        /** @var Configuration $config */
        $config = $this->callProtected('getConfigurationFromItem', ['source' => '/a', 'target' => '/b', 'status_code' => '999']);
        self::assertSame(307, $config->targetStatusCode);
    }

    #[Test]
    public function getConfigurationFromItemDefaultsTo307WhenStatusCodeMissing(): void
    {
        /** @var Configuration $config */
        $config = $this->callProtected('getConfigurationFromItem', ['source' => '/a', 'target' => '/b']);
        self::assertSame(307, $config->targetStatusCode);
    }

    // --- helper ---

    private function callProtected(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($this->subject, $method);
        return $ref->invoke($this->subject, ...$args);
    }
}
