<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Service;

use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;
use TYPO3\CMS\Frontend\Typolink\AbstractTypolinkBuilder;
use TYPO3\CMS\Frontend\Typolink\UnableToLinkException;
use TYPO3\CMS\Redirects\Service\RedirectCacheService;

class ExportService
{

    /** @var UrlMatcher */
    protected $urlMatcher;

    protected $siteCache = [];

    protected $targetUrlCache = [];

    public function __construct()
    {
        $this->urlMatcher = GeneralUtility::makeInstance(UrlMatcher::class);
    }

    public function run(bool $transformTargetUrl = false)
    {
        $redirects = $this->fetchRedirects();

        if (!$transformTargetUrl) {
            return $redirects;
        }
        foreach ($redirects as &$redirect) {
            if (isset($this->targetUrlCache[$redirect['target']])) {
                $targetUrl = $this->targetUrlCache[$redirect['target']];
            } else {
                $linkDetails = $this->resolveLinkDetailsFromLinkTarget($redirect['target']);
                $site = $this->getSiteFromDomain($redirect['source_host']);
                $targetUrl = (string)$this->getUriFromCustomLinkDetails($redirect, $site, $linkDetails, []);
                $this->targetUrlCache[$redirect['target']] = $targetUrl;
            }
            $redirect['target_url'] = $targetUrl;

        }

        return $redirects;
    }

    protected function getSiteFromDomain(string $domain): ?SiteInterface
    {

        if (isset($this->siteCache[$domain])) {
            return $this->siteCache[$domain];
        }

        if ($domain === '*') {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getAllSites()[0];
        } else {
            $siteMatcher = GeneralUtility::makeInstance(SiteMatcher::class);

            foreach (['https://', 'http://'] as $protocol) {
                $url = $protocol . $domain;
                $request = new ServerRequest($url, 'GET');

                $routeResult = $siteMatcher->matchRequest($request);
                $site = $routeResult->getSite();
                if ($site !== null && !$site instanceof NullSite) {
                    continue;
                }
            }
        }
        $this->siteCache[$domain] = $site;
        return $site;
    }

    protected function fetchRedirects(): array
    {
        return GeneralUtility::makeInstance(RedirectRepository::class)->getAllRedirects();
    }


    /**
     * Check if the current request is actually a redirect, and then process the redirect.
     *
     * @param string $redirectTarget
     *
     * @return array the link details from the linkService
     */
    protected function resolveLinkDetailsFromLinkTarget(string $redirectTarget): array
    {
        // build the target URL, take force SSL into account etc.
        $linkService = GeneralUtility::makeInstance(LinkService::class);
        try {
            $linkDetails = $linkService->resolve($redirectTarget);
            switch ($linkDetails['type']) {
                case LinkService::TYPE_URL:
                    // all set up, nothing to do
                    break;
                case LinkService::TYPE_FILE:
                    /** @var File $file */
                    $file = $linkDetails['file'];
                    if ($file instanceof File) {
                        $linkDetails['url'] = $file->getPublicUrl();
                    }
                    break;
                case LinkService::TYPE_FOLDER:
                    /** @var Folder $folder */
                    $folder = $linkDetails['folder'];
                    if ($folder instanceof Folder) {
                        $linkDetails['url'] = $folder->getPublicUrl();
                    }
                    break;
                default:
                    // we have to return the link details without having a "URL" parameter

            }
        } catch (InvalidPathException $e) {
            return [];
        }
        return $linkDetails;
    }


    /**
     * Called when TypoScript/TSFE is available, so typolink is used to generate the URL
     *
     * @param array $redirectRecord
     * @param SiteInterface|null $site
     * @param array $linkDetails
     * @param array $queryParams
     * @return UriInterface|null
     */
    protected function getUriFromCustomLinkDetails(array $redirectRecord, ?SiteInterface $site, array $linkDetails, array $queryParams): ?UriInterface
    {
        if (!isset($linkDetails['type'], $GLOBALS['TYPO3_CONF_VARS']['FE']['typolinkBuilder'][$linkDetails['type']])) {
            return null;
        }
        $controller = $this->bootFrontendController($site, $queryParams);
        /** @var AbstractTypolinkBuilder $linkBuilder */
        $linkBuilder = GeneralUtility::makeInstance(
            $GLOBALS['TYPO3_CONF_VARS']['FE']['typolinkBuilder'][$linkDetails['type']],
            $controller->cObj,
            $controller
        );
        try {
            $configuration = [
                'parameter' => (string)$redirectRecord['target'],
                'forceAbsoluteUrl' => true,
            ];
            if ($redirectRecord['force_https']) {
                $configuration['forceAbsoluteUrl.']['scheme'] = 'https';
            }
            if ($redirectRecord['keep_query_parameters']) {
                $configuration['additionalParams'] = HttpUtility::buildQueryString($queryParams, '&');
            }
            [$url] = $linkBuilder->build($linkDetails, '', '', $configuration);
            return new Uri($url);
        } catch (UnableToLinkException $e) {
            // This exception is also thrown by the DatabaseRecordTypolinkBuilder
            $url = $controller->cObj->lastTypoLinkUrl;
            if (!empty($url)) {
                return new Uri($url);
            }
            return null;
        }
    }

    /**
     * Finishing booting up TSFE, after that the following properties are available.
     *
     * Instantiating is done by the middleware stack (see Configuration/RequestMiddlewares.php)
     *
     * - TSFE->fe_user
     * - TSFE->sys_page
     * - TSFE->tmpl
     * - TSFE->config
     * - TSFE->cObj
     *
     * So a link to a page can be generated.
     *
     * @param SiteInterface|null $site
     * @param array $queryParams
     * @return TypoScriptFrontendController
     */
    protected function bootFrontendController(?SiteInterface $site, array $queryParams): TypoScriptFrontendController
    {
        if (isset($GLOBALS['TSFE'])) {
            return $GLOBALS['TSFE'];
        }
        // disable page errors
        $GLOBALS['TYPO3_CONF_VARS']['FE']['pageUnavailable_handling'] = false;
        $controller = GeneralUtility::makeInstance(
            TypoScriptFrontendController::class,
            null,
            $site ? $site->getRootPageId() : $GLOBALS['TSFE']->id,
            0
        );
        $controller->fe_user = $GLOBALS['TSFE']->fe_user ?? GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $controller->fetch_the_id();
        $controller->calculateLinkVars($queryParams);
        $GLOBALS['TSFE'] = $controller;
        $controller->getConfigArray();
        $controller->settingLanguage();
        $controller->settingLocale();
        $controller->newCObj();
        if (!$GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            $GLOBALS['TSFE'] = $controller;
        }
        if (!$GLOBALS['TSFE']->sys_page instanceof PageRepository) {
            $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        }
        return $controller;
    }


}