<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Domain\Model\Dto;

use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\SiteRouteResult;

class UrlResult
{

    /** @var SiteRouteResult */
    protected $siteRouteResult;

    /** @var PageArguments */
    protected $pageArguments;

    /**
     * UrlResult constructor.
     * @param SiteRouteResult $siteRouteResult
     * @param PageArguments $pageArguments
     */
    public function __construct(SiteRouteResult $siteRouteResult, PageArguments $pageArguments)
    {
        $this->siteRouteResult = $siteRouteResult;
        $this->pageArguments = $pageArguments;
    }

    /**
     * @return SiteRouteResult
     */
    public function getSiteRouteResult(): SiteRouteResult
    {
        return $this->siteRouteResult;
    }

    /**
     * @return PageArguments
     */
    public function getPageArguments(): PageArguments
    {
        return $this->pageArguments;
    }

    public function getLinkString(): string
    {
        return sprintf('t3://page?uid=%s', $this->pageArguments->getPageId());
    }


}
