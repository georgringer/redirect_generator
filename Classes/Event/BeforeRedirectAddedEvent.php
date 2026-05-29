<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Event;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;

/**
 * Fired just before a redirect is written to the database.
 * Listeners may modify sourceUrl, targetUrl, or the Configuration.
 * Not fired during dry runs.
 */
final class BeforeRedirectAddedEvent
{
    public function __construct(
        private string $sourceUrl,
        private string $targetUrl,
        private Configuration $configuration,
    ) {}

    public function getSourceUrl(): string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(string $sourceUrl): void
    {
        $this->sourceUrl = $sourceUrl;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(string $targetUrl): void
    {
        $this->targetUrl = $targetUrl;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }
}
