<?php

declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Event;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;

/**
 * Fired after a redirect has been written to the database.
 * Not fired during dry runs.
 */
final class AfterRedirectAddedEvent
{
    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $targetUrl,
        public readonly Configuration $configuration,
        public readonly int $uid,
    ) {}
}
