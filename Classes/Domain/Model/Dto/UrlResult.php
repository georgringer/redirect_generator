<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Domain\Model\Dto;

use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\SiteRouteResult;

readonly class UrlResult
{
    public function __construct(
        private SiteRouteResult $siteRouteResult,
        private PageArguments $pageArguments,
    ) {}

    public function getSiteRouteResult(): SiteRouteResult
    {
        return $this->siteRouteResult;
    }

    public function getPageArguments(): PageArguments
    {
        return $this->pageArguments;
    }

    public function getLinkString(): string
    {
        $parameters = ['uid' => $this->pageArguments->getPageId()];

        $languageId = $this->siteRouteResult->getLanguage()?->getLanguageId();
        if ($languageId !== null && $languageId > 0) {
            $parameters['L'] = $languageId;
        }

        return 't3://page?' . http_build_query($parameters);
    }
}
