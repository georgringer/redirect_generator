<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Domain\Model\Dto;

use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\SiteRouteResult;

class UrlInfo
{

    protected string $scheme = '';
    protected string $host = '';
    protected string $path = '';
    protected string $query = '';


    public function __construct(string $url)
    {
        $split = parse_url($url);

        $this->scheme = $split['scheme'] ?? '';
        $this->host = $split['host'] ?? '';
        $this->path = $split['path'] ?? '';
        $this->query = $split['query'] ?? '';
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getPathWithQuery(): string
    {
        $path = $this->path;
        if (!empty($this->query)) {
            $path .= '?' . $this->query;
        }
        return $path;
    }

}
