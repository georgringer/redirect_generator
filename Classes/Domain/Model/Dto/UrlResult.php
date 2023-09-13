<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Domain\Model\Dto;

use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UrlResult
{

    protected SiteRouteResult $siteRouteResult;
    protected PageArguments $pageArguments;

    public function __construct(SiteRouteResult $siteRouteResult, PageArguments $pageArguments)
    {
        $this->siteRouteResult = $siteRouteResult;
        $this->pageArguments = $pageArguments;
    }

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
        $parameters = [
            'uid' => $this->pageArguments->getPageId(),
        ];

        // language
        if ($this->siteRouteResult->getLanguage() && $this->siteRouteResult->getLanguage()->getLanguageId() > 0) {
            $parameters['L'] = $this->siteRouteResult->getLanguage()->getLanguageId();
        }

        $parameters = GeneralUtility::implodeArrayForUrl('', $parameters);
        return sprintf('t3://page?%s', trim($parameters, '&'));
    }


}
