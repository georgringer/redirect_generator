<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Tests\Unit\Domain\Model\Dto;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\UrlInfo;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrlInfoTest extends TestCase
{
    #[Test]
    public function fullUrlIsParsedCorrectly(): void
    {
        $urlInfo = new UrlInfo('https://example.com/some/path?foo=bar&baz=1');

        self::assertSame('https', $urlInfo->scheme);
        self::assertSame('example.com', $urlInfo->host);
        self::assertSame('/some/path', $urlInfo->path);
        self::assertSame('foo=bar&baz=1', $urlInfo->query);
    }

    #[Test]
    public function urlWithoutQueryHasEmptyQuery(): void
    {
        $urlInfo = new UrlInfo('https://example.com/path');

        self::assertSame('https', $urlInfo->scheme);
        self::assertSame('example.com', $urlInfo->host);
        self::assertSame('/path', $urlInfo->path);
        self::assertSame('', $urlInfo->query);
    }

    #[Test]
    public function urlWithoutHostHasEmptyHost(): void
    {
        $urlInfo = new UrlInfo('/just/a/path');

        self::assertSame('', $urlInfo->host);
        self::assertSame('/just/a/path', $urlInfo->path);
    }

    #[Test]
    public function getPathWithQueryReturnsPathAndQueryWhenQueryIsPresent(): void
    {
        $urlInfo = new UrlInfo('https://example.com/page?lang=de&id=5');

        self::assertSame('/page?lang=de&id=5', $urlInfo->getPathWithQuery());
    }

    #[Test]
    public function getPathWithQueryReturnsOnlyPathWhenQueryIsEmpty(): void
    {
        $urlInfo = new UrlInfo('https://example.com/page');

        self::assertSame('/page', $urlInfo->getPathWithQuery());
    }

    #[Test]
    public function emptyQueryStringDoesNotAddQuestionMark(): void
    {
        $urlInfo = new UrlInfo('https://example.com/page?');

        self::assertSame('/page', $urlInfo->getPathWithQuery());
    }
}
