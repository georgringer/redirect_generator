<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Domain\Model\Dto;

use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\SiteRouteResult;

class UrlInfo
{

    /** @var string */
    protected $scheme = '';

    /** @var string */
    protected $host = '';

    /** @var string */
    protected $path = '';


    public function __construct(string $url)
    {
        $split = parse_url($url);

        $this->scheme = $split['scheme'] ?? '';
        $this->host = $split['host'] ?? '';
        $this->path = $split['path'] ?? '';
    }

    /**
     * @return string
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

}
