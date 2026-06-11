<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Service;

use GeorgRinger\RedirectGenerator\Repository\RedirectRepository;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Site\SiteFinder;

class ExportService
{
    protected array $targetUrlCache = [];

    public function __construct(
        private readonly RedirectRepository $redirectRepository,
        private readonly SiteFinder $siteFinder,
        private readonly LinkService $linkService,
    ) {}

    public function run(bool $transformTargetUrl = false): array
    {
        $redirects = $this->redirectRepository->getAllRedirects();
        return $transformTargetUrl ? $this->resolveTargetUrls($redirects) : $redirects;
    }

    public function resolveTargetUrls(array $redirects): array
    {
        foreach ($redirects as &$redirect) {
            if (isset($this->targetUrlCache[$redirect['target']])) {
                $redirect['target_url'] = $this->targetUrlCache[$redirect['target']];
                continue;
            }

            $targetUrl = '';
            try {
                $linkDetails = $this->linkService->resolve($redirect['target']);
                $type = $linkDetails['type'] ?? '';

                if ($type === LinkService::TYPE_URL) {
                    $targetUrl = $linkDetails['url'] ?? '';
                } elseif ($type === LinkService::TYPE_FILE) {
                    $file = $linkDetails['file'] ?? null;
                    if ($file instanceof File) {
                        $targetUrl = $file->getPublicUrl() ?? '';
                    }
                } elseif ($type === LinkService::TYPE_FOLDER) {
                    $folder = $linkDetails['folder'] ?? null;
                    if ($folder instanceof Folder) {
                        $targetUrl = $folder->getPublicUrl() ?? '';
                    }
                } elseif ($type === LinkService::TYPE_PAGE) {
                    $uri = $this->resolvePageUri($linkDetails, (bool)($redirect['force_https'] ?? false));
                    $targetUrl = $uri !== null ? (string)$uri : '';
                }
            } catch (InvalidPathException|\Exception) {
                // ignore unresolvable targets
            }

            $this->targetUrlCache[$redirect['target']] = $targetUrl;
            $redirect['target_url'] = $targetUrl;
        }

        return $redirects;
    }

    protected function resolvePageUri(array $linkDetails, bool $forceHttps = false): ?UriInterface
    {
        $pageId = (int)($linkDetails['pageuid'] ?? 0);
        if ($pageId <= 0) {
            return null;
        }

        $language = 0;
        if (!empty($linkDetails['parameters'])) {
            parse_str($linkDetails['parameters'], $params);
            $language = (int)($params['L'] ?? 0);
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
            $siteLanguage = $site->getLanguageById($language);
            $uri = $site->getRouter()->generateUri($pageId, ['_language' => $siteLanguage]);

            if ($forceHttps && $uri->getScheme() !== 'https') {
                $uri = $uri->withScheme('https');
            }

            return $uri;
        } catch (\Exception) {
            return null;
        }
    }
}
