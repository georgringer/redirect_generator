<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Service;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\UrlResult;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

class UrlMatcher
{
    public function __construct(
        private readonly SiteMatcher $siteMatcher,
    ) {}

    /**
     * @param string $url
     * @return UrlResult
     * @throws RouteNotFoundException
     */
    public function getUrlData(string $url): UrlResult
    {
        $url = trim($url);
        $request = new ServerRequest($url, 'GET');

        $routeResult = $this->siteMatcher->matchRequest($request);

        /** @var SiteInterface $site */
        $site = $routeResult->getSite();
        if (!$site instanceof Site) {
            throw new \RuntimeException(sprintf('No site found for url: %s', $url), 1568481276);
        }
        $pageArguments = $site->getRouter()->matchRequest($request, $routeResult);

        return new UrlResult($routeResult, $pageArguments);
    }
}
