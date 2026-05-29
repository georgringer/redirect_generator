<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Tests\Unit\Domain\Model\Dto;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\UrlResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class UrlResultTest extends TestCase
{
    #[Test]
    public function getLinkStringForDefaultLanguageOmitsLParameter(): void
    {
        $language = $this->createStub(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(0);

        $routeResult = $this->createStub(SiteRouteResult::class);
        $routeResult->method('getLanguage')->willReturn($language);

        $pageArguments = $this->createStub(PageArguments::class);
        $pageArguments->method('getPageId')->willReturn(42);

        $result = new UrlResult($routeResult, $pageArguments);

        self::assertSame('t3://page?uid=42', $result->getLinkString());
    }

    #[Test]
    public function getLinkStringForNonDefaultLanguageIncludesLParameter(): void
    {
        $language = $this->createStub(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(2);

        $routeResult = $this->createStub(SiteRouteResult::class);
        $routeResult->method('getLanguage')->willReturn($language);

        $pageArguments = $this->createStub(PageArguments::class);
        $pageArguments->method('getPageId')->willReturn(5);

        $result = new UrlResult($routeResult, $pageArguments);

        self::assertSame('t3://page?uid=5&L=2', $result->getLinkString());
    }

    #[Test]
    public function getLinkStringWithNullLanguageOmitsLParameter(): void
    {
        $routeResult = $this->createStub(SiteRouteResult::class);
        $routeResult->method('getLanguage')->willReturn(null);

        $pageArguments = $this->createStub(PageArguments::class);
        $pageArguments->method('getPageId')->willReturn(1);

        $result = new UrlResult($routeResult, $pageArguments);

        self::assertSame('t3://page?uid=1', $result->getLinkString());
    }

    #[Test]
    public function gettersReturnInjectedObjects(): void
    {
        $routeResult = $this->createStub(SiteRouteResult::class);
        $routeResult->method('getLanguage')->willReturn(null);

        $pageArguments = $this->createStub(PageArguments::class);
        $pageArguments->method('getPageId')->willReturn(1);

        $result = new UrlResult($routeResult, $pageArguments);

        self::assertSame($routeResult, $result->getSiteRouteResult());
        self::assertSame($pageArguments, $result->getPageArguments());
    }
}
