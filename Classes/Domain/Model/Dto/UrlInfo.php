<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Domain\Model\Dto;

readonly class UrlInfo
{
    public string $scheme;
    public string $host;
    public string $path;
    public string $query;

    public function __construct(string $url)
    {
        $split = parse_url($url);
        $this->scheme = $split['scheme'] ?? '';
        $this->host   = $split['host']   ?? '';
        $this->path   = $split['path']   ?? '';
        $this->query  = $split['query']  ?? '';
    }

    public function getPathWithQuery(): string
    {
        return $this->query !== '' ? $this->path . '?' . $this->query : $this->path;
    }
}
